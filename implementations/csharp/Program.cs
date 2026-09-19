using MySqlConnector;
using System.Net;

var builder = WebApplication.CreateBuilder(args);
var app = builder.Build();

string Env(string key, string fallback = "") =>
    Environment.GetEnvironmentVariable(key) is { Length: > 0 } v ? v : fallback;

var baseUrl = Env("BASE_URL", "http://127.0.0.1:8080").TrimEnd('/');
var token = Env("API_TOKEN");
var minLen = int.Parse(Env("MIN_LENGTH", "8"));
var maxLen = int.Parse(Env("MAX_LENGTH", "5000"));
var cs = new MySqlConnectionStringBuilder {
    Server = Env("DB_HOST", "127.0.0.1"),
    Port = uint.Parse(Env("DB_PORT", "3306")),
    Database = Env("DB_NAME", "admin"),
    UserID = Env("DB_USER", "admin"),
    Password = Env("DB_PASS"),
    CharacterSet = "utf8mb4"
}.ConnectionString;

bool Authorized(HttpRequest req) {
    if (string.IsNullOrEmpty(token)) return false;
    var auth = req.Headers.Authorization.ToString().Trim();
    if (auth.StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase)
        && auth[7..].Trim() == token) return true;
    return req.Headers["X-API-Token"].ToString() == token;
}

string NormalizeUrl(string value) {
    value = value.Trim();
    if (value.Length > 0 && !value.Contains("://")) value = "https://" + value;
    return value;
}

bool AllowedUrl(string value) {
    if (!Uri.TryCreate(value, UriKind.Absolute, out var uri)) return false;
    if (uri.Scheme is not ("http" or "https")) return false;
    var host = uri.Host.ToLowerInvariant();
    if (host.Length == 0 || host == "localhost" || host.EndsWith(".local")) return false;
    if (IPAddress.TryParse(host, out var ip)) {
        if (IPAddress.IsLoopback(ip)) return false;
        if (ip.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork) {
            var b = ip.GetAddressBytes();
            if (b[0] == 10 || b[0] == 127 || (b[0] == 192 && b[1] == 168)
                || (b[0] == 172 && b[1] >= 16 && b[1] <= 31)) return false;
        }
    }
    return true;
}

app.MapGet("/", () => Results.Text("EEEE Long URL — C#\n"));

app.MapGet("/healthz", async () => {
    try {
        await using var conn = new MySqlConnection(cs);
        await conn.OpenAsync();
        await using var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT 1";
        await cmd.ExecuteScalarAsync();
        return Results.Json(new { ok = true, db = true });
    } catch {
        return Results.Json(new { ok = false, db = false }, statusCode: 503);
    }
});

app.MapPost("/api/create", async (HttpRequest req) => {
    if (!Authorized(req)) return Results.Json(new { success = false, error = "Unauthorized" }, statusCode: 401);

    var form = await req.ReadFormAsync();
    var target = NormalizeUrl(form["url"].ToString());
    var wanted = int.TryParse(form["length"], out var n) ? n : 50;

    if (!AllowedUrl(target)) return Results.Json(new { success = false, error = "Invalid URL" }, statusCode: 400);
    if (wanted < minLen || wanted > maxLen) return Results.Json(new { success = false, error = "Invalid length" }, statusCode: 400);

    await using var conn = new MySqlConnection(cs);
    await conn.OpenAsync();

    for (var candidate = wanted; candidate <= maxLen; candidate++) {
        try {
            await using var cmd = conn.CreateCommand();
            cmd.CommandText = "INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (@len,@url,0,1,NOW(),NOW())";
            cmd.Parameters.AddWithValue("@len", candidate);
            cmd.Parameters.AddWithValue("@url", target);
            await cmd.ExecuteNonQueryAsync();
            var id = cmd.LastInsertedId;
            return Results.Json(new {
                success = true,
                id,
                length = candidate,
                target,
                url = baseUrl + "/" + new string('e', candidate)
            });
        } catch (MySqlException ex) when (ex.Number == 1062) {
            continue;
        }
    }

    return Results.Json(new { success = false, error = "No free e-length" }, statusCode: 409);
});

app.MapGet("/{path}", async (string path, HttpRequest req) => {
    if (string.IsNullOrEmpty(path) || path.Any(c => c != 'e')) return Results.NotFound();

    await using var conn = new MySqlConnection(cs);
    await conn.OpenAsync();

    await using var cmd = conn.CreateCommand();
    cmd.CommandText = "SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=@len LIMIT 1";
    cmd.Parameters.AddWithValue("@len", path.Length);

    await using var reader = await cmd.ExecuteReaderAsync();
    if (!await reader.ReadAsync()) return Results.NotFound();

    var idOrd = reader.GetOrdinal("id");
    var targetOrd = reader.GetOrdinal("target_url");
    var enabledOrd = reader.GetOrdinal("enabled");
    var expiresOrd = reader.GetOrdinal("expires_at");

    var id = reader.GetInt64(idOrd);
    var target = reader.GetString(targetOrd);
    var enabled = reader.GetBoolean(enabledOrd);
    DateTime? expires = reader.IsDBNull(expiresOrd) ? null : reader.GetDateTime(expiresOrd);
    await reader.CloseAsync();

    if (!enabled || (expires.HasValue && expires.Value < DateTime.Now)) return Results.NotFound();

    try {
        await using var tx = await conn.BeginTransactionAsync();
        await using var upd = conn.CreateCommand();
        upd.Transaction = (MySqlTransaction)tx;
        upd.CommandText = "UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=@id";
        upd.Parameters.AddWithValue("@id", id);
        await upd.ExecuteNonQueryAsync();

        await using var log = conn.CreateCommand();
        log.Transaction = (MySqlTransaction)tx;
        log.CommandText = "INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (@id,NOW(),@ip,@ua,@ref,@uri)";
        log.Parameters.AddWithValue("@id", id);
        log.Parameters.AddWithValue("@ip", req.HttpContext.Connection.RemoteIpAddress?.ToString() ?? "");
        log.Parameters.AddWithValue("@ua", req.Headers.UserAgent.ToString()[..Math.Min(req.Headers.UserAgent.ToString().Length, 512)]);
        var referer = req.Headers.Referer.ToString();
        log.Parameters.AddWithValue("@ref", referer[..Math.Min(referer.Length, 1024)]);
        log.Parameters.AddWithValue("@uri", ("/" + path)[..Math.Min(path.Length + 1, 2048)]);
        await log.ExecuteNonQueryAsync();

        await tx.CommitAsync();
    } catch { }

    return Results.Redirect(target, permanent: false);
});

app.Run("http://0.0.0.0:" + Env("PORT", "8080"));
