import express from "express";
import mysql from "mysql2/promise";
import net from "node:net";

const app = express();
app.use(express.urlencoded({ extended: false }));
app.use(express.json());

const env = (k: string, d = "") => process.env[k] || d;
const baseUrl = env("BASE_URL", "http://127.0.0.1:8080").replace(/\/+$/, "");
const apiToken = env("API_TOKEN");
const minLen = Number(env("MIN_LENGTH", "8"));
const maxLen = Number(env("MAX_LENGTH", "5000"));

const pool = mysql.createPool({
  host: env("DB_HOST", "127.0.0.1"),
  port: Number(env("DB_PORT", "3306")),
  user: env("DB_USER", "admin"),
  password: env("DB_PASS"),
  database: env("DB_NAME", "admin"),
  charset: "utf8mb4",
  connectionLimit: 10,
});

function normalizeUrl(v: string): string {
  v = v.trim();
  if (v && !/^[a-z][a-z0-9+.-]*:\/\//i.test(v)) v = "https://" + v;
  return v;
}

function allowedUrl(v: string): boolean {
  try {
    const u = new URL(v);
    if (!["http:", "https:"].includes(u.protocol)) return false;
    const host = u.hostname.toLowerCase();
    if (!host || host === "localhost" || host.endsWith(".local")) return false;
    if (net.isIP(host)) {
      if (host === "::1" || host.startsWith("127.") || host.startsWith("10.") || host.startsWith("192.168.")) return false;
      const m = host.match(/^172\.(\d+)\./);
      if (m && Number(m[1]) >= 16 && Number(m[1]) <= 31) return false;
    }
    return true;
  } catch {
    return false;
  }
}

function authorized(req: express.Request): boolean {
  if (!apiToken) return false;
  const auth = (req.header("Authorization") || "").trim();
  if (/^Bearer\s+/i.test(auth) && auth.replace(/^Bearer\s+/i, "").trim() === apiToken) return true;
  return req.header("X-API-Token") === apiToken;
}

app.get("/healthz", async (_req, res) => {
  try {
    await pool.query("SELECT 1");
    return res.json({ ok: true, db: true });
  } catch {
    return res.status(503).json({ ok: false, db: false });
  }
});

app.post("/api/create", async (req, res) => {
  if (!authorized(req)) return res.status(401).json({ success: false, error: "Unauthorized" });

  const target = normalizeUrl(String(req.body.url || ""));
  const wanted = Number(req.body.length || 50);
  if (!allowedUrl(target)) return res.status(400).json({ success: false, error: "Invalid URL" });
  if (!Number.isInteger(wanted) || wanted < minLen || wanted > maxLen) {
    return res.status(400).json({ success: false, error: "Invalid length" });
  }

  for (let candidate = wanted; candidate <= maxLen; candidate++) {
    try {
      const [result] = await pool.execute<mysql.ResultSetHeader>(
        "INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (?,?,0,1,NOW(),NOW())",
        [candidate, target]
      );
      return res.json({
        success: true,
        id: result.insertId,
        length: candidate,
        target,
        url: baseUrl + "/" + "e".repeat(candidate),
      });
    } catch (e: any) {
      if (e?.errno === 1062) continue;
      return res.status(500).json({ success: false, error: "Database error" });
    }
  }

  return res.status(409).json({ success: false, error: "No free e-length" });
});

app.get("/", (_req, res) => res.type("text").send("EEEE Long URL — Node/TypeScript\n"));

app.get(/^\/(e+)$/, async (req, res) => {
  const path = req.params[0] as string;
  const [rows] = await pool.execute<mysql.RowDataPacket[]>(
    "SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1",
    [path.length]
  );
  const row = rows[0];
  if (!row || !row.enabled || (row.expires_at && new Date(row.expires_at) < new Date())) return res.sendStatus(404);

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    await conn.execute("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?", [row.id]);
    await conn.execute(
      "INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)",
      [row.id, req.ip || "", (req.get("user-agent") || "").slice(0, 512), (req.get("referer") || "").slice(0, 1024), req.originalUrl.slice(0, 2048)]
    );
    await conn.commit();
  } catch {
    await conn.rollback();
  } finally {
    conn.release();
  }

  return res.redirect(302, row.target_url);
});

app.use((_req, res) => res.sendStatus(404));

app.listen(Number(env("PORT", "8080")), "0.0.0.0", () => {
  console.log("Longurl Node listening");
});
