package main

import (
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net"
	"net/http"
	"net/url"
	"os"
	"strconv"
	"strings"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

type app struct {
	db      *sql.DB
	baseURL string
	token   string
	minLen  int
	maxLen  int
}

func env(k, d string) string {
	if v := os.Getenv(k); v != "" { return v }
	return d
}

func envInt(k string, d int) int {
	v, err := strconv.Atoi(env(k, strconv.Itoa(d)))
	if err != nil { return d }
	return v
}

func main() {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=true&loc=Local",
		env("DB_USER", "admin"), os.Getenv("DB_PASS"), env("DB_HOST", "127.0.0.1"),
		env("DB_PORT", "3306"), env("DB_NAME", "admin"))
	db, err := sql.Open("mysql", dsn)
	if err != nil { log.Fatal(err) }
	if err := db.Ping(); err != nil { log.Fatal(err) }

	a := &app{
		db: db, baseURL: strings.TrimRight(env("BASE_URL", "http://127.0.0.1:8080"), "/"),
		token: os.Getenv("API_TOKEN"), minLen: envInt("MIN_LENGTH", 8), maxLen: envInt("MAX_LENGTH", 5000),
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/api/create", a.create)
	mux.HandleFunc("/", a.route)

	addr := ":" + env("PORT", "8080")
	log.Printf("Longurl Go listening on %s", addr)
	log.Fatal(http.ListenAndServe(addr, mux))
}

func (a *app) route(w http.ResponseWriter, r *http.Request) {
	p := strings.Trim(r.URL.Path, "/")
	if p == "" {
		w.Header().Set("Content-Type", "text/plain; charset=utf-8")
		fmt.Fprintln(w, "EEEE Long URL — Go")
		return
	}
	if strings.Trim(p, "e") != "" {
		http.NotFound(w, r); return
	}
	var id int64
	var target string
	var enabled bool
	var expires sql.NullTime
	err := a.db.QueryRow("SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1", len(p)).
		Scan(&id, &target, &enabled, &expires)
	if err != nil || !enabled || (expires.Valid && expires.Time.Before(time.Now())) {
		http.NotFound(w, r); return
	}
	a.recordClick(r, id)
	http.Redirect(w, r, target, http.StatusFound)
}

func (a *app) create(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost { http.Error(w, "method not allowed", 405); return }
	if !a.authorized(r) { http.Error(w, "unauthorized", 401); return }
	if err := r.ParseForm(); err != nil { http.Error(w, "bad request", 400); return }

	target := normalizeURL(r.FormValue("url"))
	if !allowedURL(target) { http.Error(w, "invalid url", 400); return }
	n, _ := strconv.Atoi(r.FormValue("length"))
	if n == 0 { n = 50 }
	if n < a.minLen || n > a.maxLen { http.Error(w, "invalid length", 400); return }

	for candidate := n; candidate <= a.maxLen; candidate++ {
		res, err := a.db.Exec(
			"INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (?,?,0,1,NOW(),NOW())",
			candidate, target,
		)
		if err != nil {
			if strings.Contains(err.Error(), "Duplicate entry") { continue }
			http.Error(w, "database error", 500); return
		}
		id, _ := res.LastInsertId()
		writeJSON(w, map[string]any{
			"success": true, "id": id, "length": candidate, "target": target,
			"url": a.baseURL + "/" + strings.Repeat("e", candidate),
		})
		return
	}
	http.Error(w, "no free e-length", 409)
}

func (a *app) authorized(r *http.Request) bool {
	if a.token == "" { return false }
	auth := strings.TrimSpace(r.Header.Get("Authorization"))
	if strings.HasPrefix(strings.ToLower(auth), "bearer ") && strings.TrimSpace(auth[7:]) == a.token { return true }
	return r.Header.Get("X-API-Token") == a.token
}

func normalizeURL(s string) string {
	s = strings.TrimSpace(s)
	if s != "" && !strings.Contains(s, "://") { s = "https://" + s }
	return s
}

func allowedURL(s string) bool {
	u, err := url.ParseRequestURI(s)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Hostname() == "" { return false }
	host := strings.ToLower(u.Hostname())
	if host == "localhost" || strings.HasSuffix(host, ".local") { return false }
	if ip := net.ParseIP(host); ip != nil && (ip.IsLoopback() || ip.IsPrivate() || ip.IsUnspecified()) { return false }
	return true
}

func (a *app) recordClick(r *http.Request, id int64) {
	tx, err := a.db.Begin()
	if err != nil { return }
	defer tx.Rollback()
	if _, err = tx.Exec("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?", id); err != nil { return }
	ua := truncate(r.UserAgent(), 512)
	ref := truncate(r.Referer(), 1024)
	uri := truncate(r.URL.RequestURI(), 2048)
	ip, _, _ := net.SplitHostPort(r.RemoteAddr)
	_, err = tx.Exec("INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)", id, ip, ua, ref, uri)
	if err == nil { _ = tx.Commit() }
}

func truncate(s string, n int) string {
	if len(s) <= n { return s }
	return s[:n]
}

func writeJSON(w http.ResponseWriter, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	_ = json.NewEncoder(w).Encode(v)
}

var _ = errors.New
