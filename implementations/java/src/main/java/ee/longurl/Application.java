package ee.longurl;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.http.*;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.web.bind.annotation.*;

import java.net.*;
import java.sql.Timestamp;
import java.time.LocalDateTime;
import java.util.*;

@SpringBootApplication
@RestController
public class Application {
    private final JdbcTemplate db;
    private final String baseUrl = env("BASE_URL", "http://127.0.0.1:8080").replaceAll("/+$", "");
    private final String apiToken = System.getenv().getOrDefault("API_TOKEN", "");
    private final int minLen = Integer.parseInt(env("MIN_LENGTH", "8"));
    private final int maxLen = Integer.parseInt(env("MAX_LENGTH", "5000"));

    public Application(JdbcTemplate db) {
        this.db = db;
    }

    public static void main(String[] args) {
        System.setProperty("server.port", env("PORT", "8080"));
        String host = env("DB_HOST", "127.0.0.1");
        String port = env("DB_PORT", "3306");
        String name = env("DB_NAME", "admin");
        String user = env("DB_USER", "admin");
        String pass = System.getenv().getOrDefault("DB_PASS", "");
        System.setProperty("spring.datasource.url", "jdbc:mysql://" + host + ":" + port + "/" + name + "?useUnicode=true&characterEncoding=utf8&serverTimezone=LOCAL");
        System.setProperty("spring.datasource.username", user);
        System.setProperty("spring.datasource.password", pass);
        SpringApplication.run(Application.class, args);
    }

    @GetMapping("/")
    public String home() {
        return "EEEE Long URL — Java\n";
    }

    @PostMapping(value = "/api/create", produces = MediaType.APPLICATION_JSON_VALUE)
    public ResponseEntity<?> create(
            @RequestHeader HttpHeaders headers,
            @RequestParam String url,
            @RequestParam(defaultValue = "50") int length) {

        if (!authorized(headers)) {
            return ResponseEntity.status(401).body(Map.of("success", false, "error", "Unauthorized"));
        }

        String target = normalizeUrl(url);
        if (!allowedUrl(target)) {
            return ResponseEntity.badRequest().body(Map.of("success", false, "error", "Invalid URL"));
        }
        if (length < minLen || length > maxLen) {
            return ResponseEntity.badRequest().body(Map.of("success", false, "error", "Invalid length"));
        }

        for (int candidate = length; candidate <= maxLen; candidate++) {
            try {
                db.update("INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (?,?,0,1,NOW(),NOW())",
                        candidate, target);
                Long id = db.queryForObject("SELECT LAST_INSERT_ID()", Long.class);
                return ResponseEntity.ok(Map.of(
                        "success", true,
                        "id", id == null ? 0 : id,
                        "length", candidate,
                        "target", target,
                        "url", baseUrl + "/" + "e".repeat(candidate)
                ));
            } catch (Exception ex) {
                if (ex.getMessage() != null && ex.getMessage().contains("Duplicate entry")) continue;
                return ResponseEntity.status(500).body(Map.of("success", false, "error", "Database error"));
            }
        }

        return ResponseEntity.status(409).body(Map.of("success", false, "error", "No free e-length"));
    }

    @GetMapping("/{path:e+}")
    public ResponseEntity<Void> resolve(
            @PathVariable String path,
            @RequestHeader(value = "User-Agent", defaultValue = "") String ua,
            @RequestHeader(value = "Referer", defaultValue = "") String referer) {

        List<Map<String, Object>> rows = db.queryForList(
                "SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1",
                path.length()
        );
        if (rows.isEmpty()) return ResponseEntity.notFound().build();

        Map<String, Object> row = rows.get(0);
        boolean enabled = ((Number) row.get("enabled")).intValue() == 1;
        Timestamp expires = (Timestamp) row.get("expires_at");
        if (!enabled || (expires != null && expires.toLocalDateTime().isBefore(LocalDateTime.now()))) {
            return ResponseEntity.notFound().build();
        }

        long id = ((Number) row.get("id")).longValue();
        String target = String.valueOf(row.get("target_url"));
        try {
            db.update("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?", id);
            db.update(
                    "INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)",
                    id, "", truncate(ua, 512), truncate(referer, 1024), truncate("/" + path, 2048)
            );
        } catch (Exception ignored) {}

        return ResponseEntity.status(HttpStatus.FOUND).location(URI.create(target)).build();
    }

    private boolean authorized(HttpHeaders headers) {
        if (apiToken.isBlank()) return false;
        String auth = headers.getFirst(HttpHeaders.AUTHORIZATION);
        if (auth != null && auth.regionMatches(true, 0, "Bearer ", 0, 7)
                && auth.substring(7).trim().equals(apiToken)) return true;
        return apiToken.equals(headers.getFirst("X-API-Token"));
    }

    private static String normalizeUrl(String value) {
        String s = value == null ? "" : value.trim();
        if (!s.isEmpty() && !s.matches("(?i)^[a-z][a-z0-9+.-]*://.*")) s = "https://" + s;
        return s;
    }

    private static boolean allowedUrl(String value) {
        try {
            URI uri = URI.create(value);
            String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase();
            String host = uri.getHost() == null ? "" : uri.getHost().toLowerCase();
            if (!(scheme.equals("http") || scheme.equals("https")) || host.isBlank()) return false;
            if (host.equals("localhost") || host.endsWith(".local")) return false;
            InetAddress ip = InetAddress.getByName(host);
            return !(ip.isLoopbackAddress() || ip.isAnyLocalAddress() || ip.isLinkLocalAddress() || ip.isSiteLocalAddress());
        } catch (Exception e) {
            return false;
        }
    }

    private static String env(String key, String fallback) {
        return System.getenv().getOrDefault(key, fallback);
    }

    private static String truncate(String s, int n) {
        return s.length() <= n ? s : s.substring(0, n);
    }
}
