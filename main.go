package main

import (
	"crypto/rand"
	"database/sql"
	"embed"
	"encoding/json"
	"fmt"
	"html"
	"io/fs"
	"log"
	"math/big"
	"net"
	"net/http"
	"net/url"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/go-sql-driver/mysql"
)

//go:embed public templates sql/schema.sql
var webFS embed.FS

type app struct {
	store        *configStore
	dbMu         sync.RWMutex
	db           *sql.DB
	homeHTML     string
	assets       http.Handler
	installToken string
}

func main() {
	store := newConfigStore(".env")
	cfg := store.get()

	home, err := fs.ReadFile(webFS, "public/index.html")
	if err != nil {
		log.Fatal(err)
	}
	assetsFS, err := fs.Sub(webFS, "public/assets")
	if err != nil {
		log.Fatal(err)
	}

	a := &app{
		store:        store,
		homeHTML:     string(home),
		assets:       http.StripPrefix("/assets/", http.FileServer(http.FS(assetsFS))),
		installToken: randomHex(24),
	}

	if cfg.DBPass != "" {
		db := openDatabase(cfg)
		if db != nil {
			if err := db.Ping(); err != nil {
				log.Printf("database unavailable at startup: %v", err)
				_ = db.Close()
			} else {
				migrateSchema(db)
				a.db = db
			}
		}
	}

	mux := http.NewServeMux()
	mux.Handle("/assets/", cacheAssets(a.assets))
	mux.HandleFunc("/healthz", a.health)
	mux.HandleFunc("/api/config", a.publicConfig)
	mux.HandleFunc("/api/create", a.create)

	mux.HandleFunc("/install/", a.installPage)

	mux.HandleFunc("/admin/login", a.adminLogin)
	mux.HandleFunc("/admin/logout", a.adminLogout)
	mux.HandleFunc("/admin/link/save", a.adminSaveLink)
	mux.HandleFunc("/admin/link/toggle", a.adminToggle)
	mux.HandleFunc("/admin/link/delete", a.adminDelete)
	mux.HandleFunc("/admin/settings", a.adminSettings)
	mux.HandleFunc("/admin/token/rotate", a.adminRotateToken)
	mux.HandleFunc("/admin/password", a.adminPassword)
	mux.HandleFunc("/admin/export/links.csv", a.adminExportLinks)
	mux.HandleFunc("/admin/export/clicks.csv", a.adminExportClicks)
	mux.HandleFunc("/admin/", a.adminRoot)

	mux.HandleFunc("/", a.route)

	srv := &http.Server{
		Addr:              ":" + cfg.Port,
		Handler:           securityHeaders(mux),
		ReadHeaderTimeout: 8 * time.Second,
		ReadTimeout:       20 * time.Second,
		WriteTimeout:      30 * time.Second,
		IdleTimeout:       60 * time.Second,
	}
	log.Printf("EEEE Long URL listening on %s", srv.Addr)
	if !a.installed() {
		log.Printf("installation required: open /install/")
	}
	log.Fatal(srv.ListenAndServe())
}

func openDatabase(cfg runtimeConfig) *sql.DB {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=true&loc=Local",
		cfg.DBUser, cfg.DBPass, cfg.DBHost, cfg.DBPort, cfg.DBName)
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil
	}
	db.SetConnMaxLifetime(3 * time.Minute)
	db.SetMaxOpenConns(20)
	db.SetMaxIdleConns(10)
	return db
}

func (a *app) dbRef() *sql.DB {
	a.dbMu.RLock()
	defer a.dbMu.RUnlock()
	return a.db
}

func (a *app) replaceDB(db *sql.DB) {
	a.dbMu.Lock()
	old := a.db
	a.db = db
	a.dbMu.Unlock()
	if old != nil && old != db {
		_ = old.Close()
	}
}

func (a *app) applyConfig(cfg runtimeConfig) {
	a.store.set(cfg)
}

func (a *app) installed() bool {
	cfg := a.store.get()
	return cfg.AdminPasswordHash != "" && cfg.SessionSecret != "" && a.dbRef() != nil
}

func (a *app) health(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	db := a.dbRef()
	if db == nil || db.PingContext(r.Context()) != nil {
		writeJSONStatus(w, http.StatusServiceUnavailable, map[string]any{"ok": false, "db": false, "installed": a.installed()})
		return
	}
	writeJSON(w, map[string]any{"ok": true, "db": true, "installed": a.installed()})
}

func (a *app) publicConfig(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	cfg := a.store.get()
	writeJSON(w, map[string]any{"base_url": cfg.BaseURL, "min_length": cfg.MinLength, "max_length": cfg.MaxLength})
}

func (a *app) route(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet && r.Method != http.MethodHead {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if !a.installed() {
		http.Redirect(w, r, "/install/", http.StatusFound)
		return
	}

	path := strings.Trim(r.URL.Path, "/")
	if path == "" {
		a.serveHome(w, "home", http.StatusOK)
		return
	}
	if strings.Trim(path, "e") != "" {
		a.serveHome(w, "lost", http.StatusNotFound)
		return
	}

	db := a.dbRef()
	if db == nil {
		http.Error(w, "database unavailable", http.StatusServiceUnavailable)
		return
	}
	var id int64
	var target string
	var enabled bool
	var expires sql.NullTime
	err := db.QueryRowContext(
		r.Context(),
		"SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1",
		len(path),
	).Scan(&id, &target, &enabled, &expires)
	if err != nil || !enabled || (expires.Valid && expires.Time.Before(time.Now())) {
		a.serveHome(w, "lost", http.StatusNotFound)
		return
	}

	a.recordClick(r, id)
	http.Redirect(w, r, target, http.StatusFound)
}

func (a *app) serveHome(w http.ResponseWriter, page string, status int) {
	cfg := a.store.get()
	out := strings.NewReplacer(
		"__PAGE__", html.EscapeString(page),
		"__BASE_URL__", html.EscapeString(cfg.BaseURL),
		"__MIN_LENGTH__", strconv.Itoa(cfg.MinLength),
		"__MAX_LENGTH__", strconv.Itoa(cfg.MaxLength),
	).Replace(a.homeHTML)
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(status)
	_, _ = w.Write([]byte(out))
}

func (a *app) create(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSONStatus(w, http.StatusMethodNotAllowed, map[string]any{"success": false, "error": "Method not allowed"})
		return
	}
	if !a.installed() {
		writeJSONStatus(w, http.StatusServiceUnavailable, map[string]any{"success": false, "error": "Application is not installed"})
		return
	}
	if err := r.ParseMultipartForm(1 << 20); err != nil {
		_ = r.ParseForm()
	}

	cfg := a.store.get()
	api := hasAuthHeader(r)
	if api && !a.authorized(r) {
		writeJSONStatus(w, http.StatusUnauthorized, map[string]any{"success": false, "error": "Unauthorized"})
		return
	}

	bucket, limit := "create", cfg.CreatePerMinute
	if api {
		bucket, limit = "api", cfg.APIPerMinute
	}
	if !a.rateLimit(r, bucket, limit) {
		writeJSONStatus(w, http.StatusTooManyRequests, map[string]any{"success": false, "error": "Too many requests"})
		return
	}

	target := normalizeURL(r.FormValue("url"))
	if !allowedURL(target) {
		writeJSONStatus(w, http.StatusBadRequest, map[string]any{"success": false, "error": "Invalid URL"})
		return
	}
	wanted, _ := strconv.Atoi(r.FormValue("length"))
	if wanted == 0 {
		wanted = 100
	}
	if wanted < cfg.MinLength || wanted > cfg.MaxLength {
		writeJSONStatus(w, http.StatusBadRequest, map[string]any{"success": false, "error": "Invalid length"})
		return
	}

	tried := map[int]bool{}
	for attempt := 0; attempt < 32; attempt++ {
		length, err := a.pickNearby(r, wanted, tried, cfg)
		if err != nil {
			break
		}
		tried[length] = true

		db := a.dbRef()
		if db == nil {
			break
		}
		res, err := db.ExecContext(
			r.Context(),
			"INSERT INTO links (e_length,target_url,created_ip,clicks,enabled,created_at,updated_at) VALUES (?,?,?,0,1,NOW(),NOW())",
			length, target, clientIP(r),
		)
		if err != nil {
			if isDup(err) {
				continue
			}
			writeJSONStatus(w, http.StatusInternalServerError, map[string]any{"success": false, "error": "Database error"})
			return
		}
		id, _ := res.LastInsertId()
		writeJSON(w, map[string]any{
			"success": true,
			"id":      id,
			"length":  length,
			"target":  target,
			"url":     strings.TrimRight(cfg.BaseURL, "/") + "/" + strings.Repeat("e", length),
		})
		return
	}
	writeJSONStatus(w, http.StatusConflict, map[string]any{"success": false, "error": "No free e-length"})
}

func (a *app) pickNearby(r *http.Request, wanted int, tried map[int]bool, cfg runtimeConfig) (int, error) {
	for _, radius := range []int{0, 4, 12, 32, 64, 128, 256} {
		low, high := max(cfg.MinLength, wanted-radius), min(cfg.MaxLength, wanted+radius)
		used, err := a.usedInRange(r, low, high)
		if err != nil {
			return 0, err
		}
		free := make([]int, 0)
		for n := low; n <= high; n++ {
			if !used[n] && !tried[n] {
				free = append(free, n)
			}
		}
		if len(free) == 0 {
			continue
		}
		sort.Slice(free, func(i, j int) bool {
			di, dj := abs(free[i]-wanted), abs(free[j]-wanted)
			if di == dj {
				return free[i] < free[j]
			}
			return di < dj
		})
		pool := min(len(free), max(4, radius+1))
		return free[randInt(pool)], nil
	}
	for n := wanted; n <= cfg.MaxLength; n++ {
		if !tried[n] {
			return n, nil
		}
	}
	return 0, fmt.Errorf("no free length")
}

func (a *app) usedInRange(r *http.Request, low, high int) (map[int]bool, error) {
	db := a.dbRef()
	if db == nil {
		return nil, fmt.Errorf("database unavailable")
	}
	rows, err := db.QueryContext(r.Context(), "SELECT e_length FROM links WHERE e_length>=? AND e_length<=?", low, high)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	used := map[int]bool{}
	for rows.Next() {
		var n int
		if err := rows.Scan(&n); err != nil {
			return nil, err
		}
		used[n] = true
	}
	return used, rows.Err()
}

func (a *app) rateLimit(r *http.Request, bucket string, limit int) bool {
	if limit <= 0 {
		return true
	}
	db := a.dbRef()
	if db == nil {
		return false
	}
	ip := clientIP(r)
	window := time.Now().Truncate(time.Minute)
	_, err := db.ExecContext(
		r.Context(),
		"INSERT INTO rate_limits (bucket,ip,hits,window_start) VALUES (?,?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1",
		bucket, ip, window,
	)
	if err != nil {
		return false
	}

	var hits int
	err = db.QueryRowContext(
		r.Context(),
		"SELECT hits FROM rate_limits WHERE bucket=? AND ip=? AND window_start=? LIMIT 1",
		bucket, ip, window,
	).Scan(&hits)
	return err == nil && hits <= limit
}

func hasAuthHeader(r *http.Request) bool {
	return strings.TrimSpace(r.Header.Get("Authorization")) != "" || strings.TrimSpace(r.Header.Get("X-API-Token")) != ""
}

func (a *app) authorized(r *http.Request) bool {
	cfg := a.store.get()
	if cfg.APIToken == "" {
		return false
	}
	auth := strings.TrimSpace(r.Header.Get("Authorization"))
	if strings.HasPrefix(strings.ToLower(auth), "bearer ") && strings.TrimSpace(auth[7:]) == cfg.APIToken {
		return true
	}
	return r.Header.Get("X-API-Token") == cfg.APIToken
}

func normalizeURL(s string) string {
	s = strings.TrimSpace(s)
	if s != "" && !strings.Contains(s, "://") {
		s = "https://" + s
	}
	return s
}

func allowedURL(s string) bool {
	u, err := url.ParseRequestURI(s)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Hostname() == "" {
		return false
	}
	host := strings.ToLower(u.Hostname())
	if host == "localhost" || strings.HasSuffix(host, ".local") {
		return false
	}
	if ip := net.ParseIP(host); ip != nil {
		if ip.IsLoopback() || ip.IsPrivate() || ip.IsUnspecified() || ip.IsLinkLocalUnicast() {
			return false
		}
	}
	return true
}

func (a *app) recordClick(r *http.Request, id int64) {
	db := a.dbRef()
	if db == nil {
		return
	}
	tx, err := db.Begin()
	if err != nil {
		return
	}
	defer tx.Rollback()
	if _, err = tx.Exec("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?", id); err != nil {
		return
	}
	_, err = tx.Exec(
		"INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)",
		id, clientIP(r), truncate(r.UserAgent(), 512), truncate(r.Referer(), 1024), truncate(r.URL.RequestURI(), 2048),
	)
	if err == nil {
		_ = tx.Commit()
	}
}

func clientIP(r *http.Request) string {
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		ip := strings.TrimSpace(strings.Split(xff, ",")[0])
		if net.ParseIP(ip) != nil {
			return ip
		}
	}
	if rip := strings.TrimSpace(r.Header.Get("X-Real-IP")); net.ParseIP(rip) != nil {
		return rip
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err == nil && net.ParseIP(host) != nil {
		return host
	}
	return "0.0.0.0"
}

func isDup(err error) bool {
	if me, ok := err.(*mysql.MySQLError); ok {
		return me.Number == 1062
	}
	return strings.Contains(strings.ToLower(err.Error()), "duplicate")
}

func truncate(s string, n int) string {
	if len(s) <= n {
		return s
	}
	return s[:n]
}

func writeJSON(w http.ResponseWriter, v any) {
	writeJSONStatus(w, http.StatusOK, v)
}

func writeJSONStatus(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func randInt(n int) int {
	if n <= 1 {
		return 0
	}
	v, err := rand.Int(rand.Reader, big.NewInt(int64(n)))
	if err != nil {
		return 0
	}
	return int(v.Int64())
}

func abs(n int) int {
	if n < 0 {
		return -n
	}
	return n
}

func cacheAssets(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Cache-Control", "public, max-age=3600")
		next.ServeHTTP(w, r)
	})
}

func securityHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("X-Content-Type-Options", "nosniff")
		w.Header().Set("Referrer-Policy", "strict-origin-when-cross-origin")
		w.Header().Set("X-Frame-Options", "DENY")
		w.Header().Set("Permissions-Policy", "camera=(), microphone=(), geolocation=()")
		next.ServeHTTP(w, r)
	})
}
