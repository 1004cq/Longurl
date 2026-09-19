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
	"os"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/go-sql-driver/mysql"
)

//go:embed public
var webFS embed.FS

type app struct {
	db *sql.DB
	baseURL string
	apiToken string
	minLen int
	maxLen int
	createPerMinute int
	apiPerMinute int
	homeHTML string
	assets http.Handler
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
		env("DB_USER","admin"), os.Getenv("DB_PASS"), env("DB_HOST","127.0.0.1"),
		env("DB_PORT","3306"), env("DB_NAME","admin"))
	db, err := sql.Open("mysql", dsn)
	if err != nil { log.Fatal(err) }
	db.SetConnMaxLifetime(3*time.Minute)
	db.SetMaxOpenConns(20)
	db.SetMaxIdleConns(10)
	if err := db.Ping(); err != nil { log.Fatal(err) }

	home, err := fs.ReadFile(webFS, "public/index.html")
	if err != nil { log.Fatal(err) }
	assetsFS, err := fs.Sub(webFS, "public/assets")
	if err != nil { log.Fatal(err) }

	a := &app{
		db:db,
		baseURL:strings.TrimRight(env("BASE_URL","http://127.0.0.1:8080"),"/"),
		apiToken:os.Getenv("API_TOKEN"),
		minLen:envInt("MIN_LENGTH",8),
		maxLen:envInt("MAX_LENGTH",5000),
		createPerMinute:envInt("CREATE_PER_MINUTE",20),
		apiPerMinute:envInt("API_PER_MINUTE",60),
		homeHTML:string(home),
		assets:http.StripPrefix("/assets/", http.FileServer(http.FS(assetsFS))),
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", a.health)
	mux.HandleFunc("/api/config", a.config)
	mux.HandleFunc("/api/create", a.create)
	mux.Handle("/assets/", cacheAssets(a.assets))
	mux.HandleFunc("/", a.route)

	srv := &http.Server{
		Addr:":"+env("PORT","8080"), Handler:securityHeaders(mux),
		ReadHeaderTimeout:8*time.Second, ReadTimeout:15*time.Second,
		WriteTimeout:20*time.Second, IdleTimeout:60*time.Second,
	}
	log.Printf("EEEE Long URL listening on %s", srv.Addr)
	log.Fatal(srv.ListenAndServe())
}

func (a *app) health(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet { http.Error(w,"method not allowed",405); return }
	if err := a.db.PingContext(r.Context()); err != nil {
		writeJSONStatus(w,503,map[string]any{"ok":false,"db":false}); return
	}
	writeJSON(w,map[string]any{"ok":true,"db":true})
}

func (a *app) config(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet { http.Error(w,"method not allowed",405); return }
	writeJSON(w,map[string]any{"base_url":a.baseURL,"min_length":a.minLen,"max_length":a.maxLen})
}

func (a *app) route(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet && r.Method != http.MethodHead { http.Error(w,"method not allowed",405); return }
	p := strings.Trim(r.URL.Path,"/")
	if p == "" { a.serveHome(w,"home",200); return }
	if strings.Trim(p,"e") != "" { a.serveHome(w,"lost",404); return }

	var id int64
	var target string
	var enabled bool
	var expires sql.NullTime
	err := a.db.QueryRowContext(r.Context(),"SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1",len(p)).
		Scan(&id,&target,&enabled,&expires)
	if err != nil || !enabled || (expires.Valid && expires.Time.Before(time.Now())) {
		a.serveHome(w,"lost",404); return
	}
	go a.recordClick(r,id)
	http.Redirect(w,r,target,http.StatusFound)
}

func (a *app) serveHome(w http.ResponseWriter, page string, status int) {
	out := strings.NewReplacer(
		"__PAGE__",html.EscapeString(page),
		"__BASE_URL__",html.EscapeString(a.baseURL),
		"__MIN_LENGTH__",strconv.Itoa(a.minLen),
		"__MAX_LENGTH__",strconv.Itoa(a.maxLen),
	).Replace(a.homeHTML)
	w.Header().Set("Content-Type","text/html; charset=utf-8")
	w.WriteHeader(status)
	_, _ = w.Write([]byte(out))
}

func (a *app) create(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost { writeJSONStatus(w,405,map[string]any{"success":false,"error":"Method not allowed"}); return }
	if err := r.ParseMultipartForm(1<<20); err != nil { _ = r.ParseForm() }

	api := hasAuthHeader(r)
	if api && !a.authorized(r) { writeJSONStatus(w,401,map[string]any{"success":false,"error":"Unauthorized"}); return }
	bucket, limit := "create", a.createPerMinute
	if api { bucket, limit = "api", a.apiPerMinute }
	if !a.rateLimit(r,bucket,limit) { writeJSONStatus(w,429,map[string]any{"success":false,"error":"Too many requests"}); return }

	target := normalizeURL(r.FormValue("url"))
	if !allowedURL(target) { writeJSONStatus(w,400,map[string]any{"success":false,"error":"Invalid URL"}); return }
	wanted,_ := strconv.Atoi(r.FormValue("length"))
	if wanted == 0 { wanted = 100 }
	if wanted < a.minLen || wanted > a.maxLen { writeJSONStatus(w,400,map[string]any{"success":false,"error":"Invalid length"}); return }

	tried := map[int]bool{}
	for attempt:=0; attempt<32; attempt++ {
		length,err := a.pickNearby(r,wanted,tried)
		if err != nil { break }
		tried[length]=true
		res,err := a.db.ExecContext(r.Context(),"INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (?,?,0,1,NOW(),NOW())",length,target)
		if err != nil {
			if isDup(err) { continue }
			writeJSONStatus(w,500,map[string]any{"success":false,"error":"Database error"}); return
		}
		id,_ := res.LastInsertId()
		writeJSON(w,map[string]any{"success":true,"id":id,"length":length,"target":target,"url":a.baseURL+"/"+strings.Repeat("e",length)})
		return
	}
	writeJSONStatus(w,409,map[string]any{"success":false,"error":"No free e-length"})
}

func (a *app) pickNearby(r *http.Request, wanted int, tried map[int]bool) (int,error) {
	for _,radius := range []int{0,4,12,32,64,128,256} {
		low,high := max(a.minLen,wanted-radius), min(a.maxLen,wanted+radius)
		used,err := a.usedInRange(r,low,high)
		if err != nil { return 0,err }
		free := make([]int,0)
		for n:=low;n<=high;n++ { if !used[n] && !tried[n] { free=append(free,n) } }
		if len(free)==0 { continue }
		sort.Slice(free,func(i,j int) bool {
			di,dj := abs(free[i]-wanted), abs(free[j]-wanted)
			if di==dj { return free[i]<free[j] }
			return di<dj
		})
		pool := min(len(free),max(4,radius+1))
		return free[randInt(pool)],nil
	}
	for n:=wanted;n<=a.maxLen;n++ { if !tried[n] { return n,nil } }
	return 0,fmt.Errorf("no free length")
}

func (a *app) usedInRange(r *http.Request, low, high int) (map[int]bool,error) {
	rows,err := a.db.QueryContext(r.Context(),"SELECT e_length FROM links WHERE e_length>=? AND e_length<=?",low,high)
	if err != nil { return nil,err }
	defer rows.Close()
	used := map[int]bool{}
	for rows.Next() { var n int; if err:=rows.Scan(&n); err!=nil { return nil,err }; used[n]=true }
	return used,rows.Err()
}

func (a *app) rateLimit(r *http.Request, bucket string, limit int) bool {
	if limit<=0 { return true }
	ip := clientIP(r)
	window := time.Now().Truncate(time.Minute)
	_,err := a.db.ExecContext(r.Context(),
		"INSERT INTO rate_limits (bucket,ip,hits,window_start) VALUES (?,?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1",
		bucket,ip,window)
	if err != nil { return false }
	var hits int
	err = a.db.QueryRowContext(r.Context(),"SELECT hits FROM rate_limits WHERE bucket=? AND ip=? AND window_start=? LIMIT 1",bucket,ip,window).Scan(&hits)
	return err==nil && hits<=limit
}

func hasAuthHeader(r *http.Request) bool {
	return strings.TrimSpace(r.Header.Get("Authorization"))!="" || strings.TrimSpace(r.Header.Get("X-API-Token"))!=""
}
func (a *app) authorized(r *http.Request) bool {
	if a.apiToken=="" { return false }
	auth := strings.TrimSpace(r.Header.Get("Authorization"))
	if strings.HasPrefix(strings.ToLower(auth),"bearer ") && strings.TrimSpace(auth[7:])==a.apiToken { return true }
	return r.Header.Get("X-API-Token")==a.apiToken
}
func normalizeURL(s string) string {
	s=strings.TrimSpace(s)
	if s!="" && !strings.Contains(s,"://") { s="https://"+s }
	return s
}
func allowedURL(s string) bool {
	u,err := url.ParseRequestURI(s)
	if err!=nil || (u.Scheme!="http"&&u.Scheme!="https") || u.Hostname()=="" { return false }
	host := strings.ToLower(u.Hostname())
	if host=="localhost" || strings.HasSuffix(host,".local") { return false }
	if ip:=net.ParseIP(host); ip!=nil {
		if ip.IsLoopback() || ip.IsPrivate() || ip.IsUnspecified() || ip.IsLinkLocalUnicast() { return false }
	}
	return true
}

func (a *app) recordClick(r *http.Request, id int64) {
	tx,err := a.db.Begin()
	if err!=nil { return }
	defer tx.Rollback()
	if _,err=tx.Exec("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?",id); err!=nil { return }
	_,err=tx.Exec("INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)",
		id,clientIP(r),truncate(r.UserAgent(),512),truncate(r.Referer(),1024),truncate(r.URL.RequestURI(),2048))
	if err==nil { _=tx.Commit() }
}

func clientIP(r *http.Request) string {
	if xff:=r.Header.Get("X-Forwarded-For"); xff!="" {
		ip:=strings.TrimSpace(strings.Split(xff,",")[0]); if net.ParseIP(ip)!=nil { return ip }
	}
	if rip:=strings.TrimSpace(r.Header.Get("X-Real-IP")); net.ParseIP(rip)!=nil { return rip }
	host,_,err:=net.SplitHostPort(r.RemoteAddr)
	if err==nil && net.ParseIP(host)!=nil { return host }
	return "0.0.0.0"
}
func isDup(err error) bool {
	if me,ok:=err.(*mysql.MySQLError); ok { return me.Number==1062 }
	return strings.Contains(strings.ToLower(err.Error()),"duplicate")
}
func truncate(s string,n int) string { if len(s)<=n { return s }; return s[:n] }
func writeJSON(w http.ResponseWriter,v any) { writeJSONStatus(w,200,v) }
func writeJSONStatus(w http.ResponseWriter,status int,v any) {
	w.Header().Set("Content-Type","application/json; charset=utf-8"); w.WriteHeader(status); _=json.NewEncoder(w).Encode(v)
}
func randInt(n int) int {
	if n<=1 { return 0 }
	v,err:=rand.Int(rand.Reader,big.NewInt(int64(n))); if err!=nil { return 0 }; return int(v.Int64())
}
func abs(n int) int { if n<0 { return -n }; return n }

func cacheAssets(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter,r *http.Request){ w.Header().Set("Cache-Control","public, max-age=3600"); next.ServeHTTP(w,r) })
}
func securityHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter,r *http.Request){
		w.Header().Set("X-Content-Type-Options","nosniff")
		w.Header().Set("Referrer-Policy","strict-origin-when-cross-origin")
		w.Header().Set("X-Frame-Options","DENY")
		next.ServeHTTP(w,r)
	})
}
