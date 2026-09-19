package main

import (
	"crypto/rand"
	"database/sql"
	"encoding/json"
	"fmt"
	"log"
	"math/big"
	"net"
	"net/http"
	"net/url"
	"os"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/go-sql-driver/mysql"
)

type app struct {
	db      *sql.DB
	baseURL string
	token   string
	minLen  int
	maxLen  int
}

func env(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}

func envInt(k string, d int) int {
	v, err := strconv.Atoi(env(k, strconv.Itoa(d)))
	if err != nil {
		return d
	}
	return v
}

func main() {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=true&loc=Local",
		env("DB_USER", "eeee_user"), os.Getenv("DB_PASS"), env("DB_HOST", "127.0.0.1"),
		env("DB_PORT", "3306"), env("DB_NAME", "eeee_longurl"))
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		log.Fatal(err)
	}
	if err := db.Ping(); err != nil {
		log.Fatal(err)
	}

	a := &app{
		db:      db,
		baseURL: strings.TrimRight(env("BASE_URL", "http://127.0.0.1:8080"), "/"),
		token:   os.Getenv("API_TOKEN"),
		minLen:  envInt("MIN_LENGTH", 8),
		maxLen:  envInt("MAX_LENGTH", 5000),
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", a.health)
	mux.HandleFunc("/api/create", a.create)
	mux.HandleFunc("/", a.route)

	addr := ":" + env("PORT", "8080")
	log.Printf("Longurl Go listening on %s", addr)
	log.Fatal(http.ListenAndServe(addr, mux))
}

func (a *app) health(w http.ResponseWriter, r *http.Request) {
	if err := a.db.PingContext(r.Context()); err != nil {
		w.WriteHeader(http.StatusServiceUnavailable)
		writeJSON(w, map[string]any{"ok": false, "db": false})
		return
	}
	writeJSON(w, map[string]any{"ok": true, "db": true})
}

func (a *app) route(w http.ResponseWriter, r *http.Request) {
	p := strings.Trim(r.URL.Path, "/")
	if p == "" {
		w.Header().Set("Content-Type", "text/plain; charset=utf-8")
		fmt.Fprintln(w, "EEEE Long URL — Go")
		return
	}
	if strings.Trim(p, "e") != "" {
		http.NotFound(w, r)
		return
	}
	var id int64
	var target string
	var enabled bool
	var expires sql.NullTime
	err := a.db.QueryRow("SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1", len(p)).
		Scan(&id, &target, &enabled, &expires)
	if err != nil || !enabled || (expires.Valid && expires.Time.Before(time.Now())) {
		http.NotFound(w, r)
		return
	}
	a.recordClick(r, id)
	http.Redirect(w, r, target, http.StatusFound)
}

func (a *app) create(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", 405)
		return
	}
	_ = r.ParseForm()
	if !a.authorized(r) {
		http.Error(w, "unauthorized", 401)
		return
	}

	target := normalizeURL(r.FormValue("url"))
	if !allowedURL(target) {
		http.Error(w, "invalid url", 400)
		return
	}
	n, _ := strconv.Atoi(r.FormValue("length"))
	if n == 0 {
		n = 100
	}
	if n < a.minLen || n > a.maxLen {
		http.Error(w, "invalid length", 400)
		return
	}

	tried := map[int]bool{}
	for attempt := 0; attempt < 24; attempt++ {
		length, err := a.pickNearby(n, tried)
		if err != nil {
			break
		}
		tried[length] = true
		res, err := a.db.Exec(
			"INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (?,?,0,1,NOW(),NOW())",
			length, target,
		)
		if err != nil {
			if isDup(err) {
				continue
			}
			http.Error(w, "database error", 500)
			return
		}
		id, _ := res.LastInsertId()
		writeJSON(w, map[string]any{
			"success": true, "id": id, "length": length, "target": target,
			"url": a.baseURL + "/" + strings.Repeat("e", length),
		})
		return
	}
	http.Error(w, "no free e-length", 409)
}

func (a *app) pickNearby(wanted int, tried map[int]bool) (int, error) {
	radii := []int{0, 4, 12, 32, 64, 128, 256}
	for _, radius := range radii {
		low := max(a.minLen, wanted-radius)
		high := min(a.maxLen, wanted+radius)
		used, err := a.usedInRange(low, high)
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
			di := abs(free[i] - wanted)
			dj := abs(free[j] - wanted)
			if di == dj {
				return free[i] < free[j]
			}
			return di < dj
		})
		pool := min(len(free), max(4, radius+1))
		idx := randInt(pool)
		return free[idx], nil
	}
	for n := wanted; n <= a.maxLen; n++ {
		if !tried[n] {
			return n, nil
		}
	}
	return 0, fmt.Errorf("full")
}

func (a *app) usedInRange(low, high int) (map[int]bool, error) {
	rows, err := a.db.Query("SELECT e_length FROM links WHERE e_length>=? AND e_length<=?", low, high)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	used := map[int]bool{}
	for rows.Next() {
		var n int
		if err := rows.Scan(&n); err == nil {
			used[n] = true
		}
	}
	return used, nil
}

func (a *app) authorized(r *http.Request) bool {
	if a.token == "" {
		return false
	}
	auth := strings.TrimSpace(r.Header.Get("Authorization"))
	if strings.HasPrefix(strings.ToLower(auth), "bearer ") && strings.TrimSpace(auth[7:]) == a.token {
		return true
	}
	if r.Header.Get("X-API-Token") == a.token {
		return true
	}
	return r.FormValue("token") == a.token
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
	if ip := net.ParseIP(host); ip != nil && (ip.IsLoopback() || ip.IsPrivate() || ip.IsUnspecified()) {
		return false
	}
	return true
}

func (a *app) recordClick(r *http.Request, id int64) {
	tx, err := a.db.Begin()
	if err != nil {
		return
	}
	defer tx.Rollback()
	if _, err = tx.Exec("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?", id); err != nil {
		return
	}
	ua := truncate(r.UserAgent(), 512)
	ref := truncate(r.Referer(), 1024)
	uri := truncate(r.URL.RequestURI(), 2048)
	_, err = tx.Exec("INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)",
		id, clientIP(r), ua, ref, uri)
	if err == nil {
		_ = tx.Commit()
	}
}

func clientIP(r *http.Request) string {
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		return strings.TrimSpace(strings.Split(xff, ",")[0])
	}
	if rip := strings.TrimSpace(r.Header.Get("X-Real-IP")); rip != "" {
		return rip
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return host
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
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
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
