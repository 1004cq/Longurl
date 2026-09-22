package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/csv"
	"encoding/hex"
	"html/template"
	"net/http"
	"strconv"
	"strings"
	"time"
)

const adminCookieName = "eeee_admin"

type adminLink struct {
	ID        int64
	Length    int
	Target    string
	CreatedIP string
	Clicks    int64
	Enabled   bool
	CreatedAt time.Time
	ExpiresAt string
	LongURL   string
}

type adminLog struct {
	ClickedAt time.Time
	Length    int
	Target    string
	IP        string
	Referer   string
}

type adminDay struct {
	Day    string
	Clicks int64
	Height int64
}

type adminPageData struct {
	View         string
	CSRF         string
	Flash        string
	Query        string
	Page         int
	Pages        int
	TotalLinks   int64
	ActiveLinks  int64
	TotalClicks  int64
	TodayClicks  int64
	Links        []adminLink
	Edit         *adminLink
	Recent       []adminLog
	Days         []adminDay
	APITokenMask string
	BaseURL      string
	MinLength    int
	MaxLength    int
	CreatePerMin int
	APIPerMin    int
}

var adminTemplates = template.Must(template.New("admin.html").Funcs(template.FuncMap{
	"date": func(t time.Time) string {
		if t.IsZero() {
			return ""
		}
		return t.Format("2006-01-02 15:04")
	},
	"add": func(a, b int) int { return a + b },
	"sub": func(a, b int) int { return a - b },
}).ParseFS(webFS, "templates/admin.html", "templates/login.html"))

func (a *app) adminLogin(w http.ResponseWriter, r *http.Request) {
	if !a.installed() {
		http.Redirect(w, r, "/install/", http.StatusFound)
		return
	}
	if a.isAdmin(r) {
		http.Redirect(w, r, "/admin/", http.StatusFound)
		return
	}
	if r.Method == http.MethodPost {
		_ = r.ParseForm()
		if a.dbRef() != nil && !a.rateLimit(r, "login", 8) {
			a.renderLogin(w, "Too many attempts. Try again later.")
			return
		}
		cfg := a.store.get()
		if passwordOK(cfg.AdminPasswordHash, r.FormValue("password")) {
			a.setAdminSession(w, r)
			http.Redirect(w, r, "/admin/", http.StatusFound)
			return
		}
		a.renderLogin(w, "Invalid password.")
		return
	}
	a.renderLogin(w, "")
}

func (a *app) renderLogin(w http.ResponseWriter, errMsg string) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_ = adminTemplates.ExecuteTemplate(w, "login.html", map[string]string{"Error": errMsg})
}

func (a *app) adminRoot(w http.ResponseWriter, r *http.Request) {
	if !a.isAdmin(r) {
		http.Redirect(w, r, "/admin/login", http.StatusFound)
		return
	}
	db := a.dbRef()
	if db == nil {
		http.Error(w, "database unavailable", http.StatusServiceUnavailable)
		return
	}

	view := r.URL.Query().Get("view")
	switch view {
	case "", "dashboard":
		view = "dashboard"
	case "links", "analytics", "visits", "settings":
	default:
		view = "dashboard"
	}

	data := adminPageData{View: view, CSRF: a.csrfToken(r), Flash: r.URL.Query().Get("ok")}
	cfg := a.store.get()
	data.BaseURL, data.MinLength, data.MaxLength = cfg.BaseURL, cfg.MinLength, cfg.MaxLength
	data.CreatePerMin, data.APIPerMin = cfg.CreatePerMinute, cfg.APIPerMinute
	data.APITokenMask = maskToken(cfg.APIToken)

	_ = db.QueryRow("SELECT COUNT(*),COALESCE(SUM(clicks),0),COALESCE(SUM(enabled=1),0) FROM links").Scan(&data.TotalLinks, &data.TotalClicks, &data.ActiveLinks)
	_ = db.QueryRow("SELECT COUNT(*) FROM click_logs WHERE clicked_at>=CURDATE()").Scan(&data.TodayClicks)

	if view == "links" {
		data.Query = strings.TrimSpace(r.URL.Query().Get("q"))
		data.Page, _ = strconv.Atoi(r.URL.Query().Get("page"))
		if data.Page < 1 {
			data.Page = 1
		}
		data.Links, data.Pages = a.fetchLinks(data.Query, data.Page, 25)
		if editID, _ := strconv.ParseInt(r.URL.Query().Get("edit"), 10, 64); editID > 0 {
			data.Edit = a.fetchLink(editID)
		}
	} else {
		data.Links, _ = a.fetchLinks("", 1, 10)
	}
	if view == "analytics" || view == "visits" {
		data.Recent = a.fetchRecentLogs(200)
		data.Days = a.fetchDays(7)
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := adminTemplates.ExecuteTemplate(w, "admin.html", data); err != nil {
		http.Error(w, "template error", http.StatusInternalServerError)
	}
}

func (a *app) fetchLinks(q string, page, perPage int) ([]adminLink, int) {
	db := a.dbRef()
	if db == nil {
		return nil, 1
	}
	where := ""
	args := []any{}
	if q != "" {
		where = " WHERE target_url LIKE ? OR created_ip LIKE ? OR CAST(e_length AS CHAR)=?"
		args = append(args, "%"+q+"%", "%"+q+"%", q)
	}
	var count int
	_ = db.QueryRow("SELECT COUNT(*) FROM links"+where, args...).Scan(&count)
	pages := (count + perPage - 1) / perPage
	if pages < 1 {
		pages = 1
	}
	if page > pages {
		page = pages
	}
	args = append(args, perPage, (page-1)*perPage)
	rows, err := db.Query("SELECT id,e_length,target_url,created_ip,clicks,enabled,created_at,expires_at FROM links"+where+" ORDER BY id DESC LIMIT ? OFFSET ?", args...)
	if err != nil {
		return nil, pages
	}
	defer rows.Close()

	cfg := a.store.get()
	var out []adminLink
	for rows.Next() {
		var l adminLink
		var expires *time.Time
		if err := rows.Scan(&l.ID, &l.Length, &l.Target, &l.CreatedIP, &l.Clicks, &l.Enabled, &l.CreatedAt, &expires); err != nil {
			continue
		}
		if expires != nil {
			l.ExpiresAt = expires.Format("2006-01-02 15:04:05")
		}
		l.LongURL = strings.TrimRight(cfg.BaseURL, "/") + "/" + strings.Repeat("e", l.Length)
		out = append(out, l)
	}
	return out, pages
}

func (a *app) fetchLink(id int64) *adminLink {
	db := a.dbRef()
	if db == nil {
		return nil
	}
	var l adminLink
	var expires *time.Time
	err := db.QueryRow("SELECT id,e_length,target_url,created_ip,clicks,enabled,created_at,expires_at FROM links WHERE id=? LIMIT 1", id).
		Scan(&l.ID, &l.Length, &l.Target, &l.CreatedIP, &l.Clicks, &l.Enabled, &l.CreatedAt, &expires)
	if err != nil {
		return nil
	}
	if expires != nil {
		l.ExpiresAt = expires.Format("2006-01-02 15:04:05")
	}
	cfg := a.store.get()
	l.LongURL = strings.TrimRight(cfg.BaseURL, "/") + "/" + strings.Repeat("e", l.Length)
	return &l
}

func (a *app) fetchRecentLogs(limit int) []adminLog {
	db := a.dbRef()
	if db == nil {
		return nil
	}
	rows, err := db.Query("SELECT c.clicked_at,l.e_length,l.target_url,c.ip,c.referer FROM click_logs c JOIN links l ON l.id=c.link_id ORDER BY c.id DESC LIMIT ?", limit)
	if err != nil {
		return nil
	}
	defer rows.Close()
	var out []adminLog
	for rows.Next() {
		var x adminLog
		if rows.Scan(&x.ClickedAt, &x.Length, &x.Target, &x.IP, &x.Referer) == nil {
			out = append(out, x)
		}
	}
	return out
}

func (a *app) fetchDays(days int) []adminDay {
	db := a.dbRef()
	counts := map[string]int64{}
	var maxClicks int64
	if db != nil {
		rows, err := db.Query("SELECT DATE_FORMAT(clicked_at,'%Y-%m-%d'),COUNT(*) FROM click_logs WHERE clicked_at>=DATE_SUB(CURDATE(),INTERVAL ? DAY) GROUP BY DATE(clicked_at)", days-1)
		if err == nil {
			defer rows.Close()
			for rows.Next() {
				var d string
				var n int64
				if rows.Scan(&d, &n) == nil {
					counts[d] = n
					if n > maxClicks {
						maxClicks = n
					}
				}
			}
		}
	}
	out := make([]adminDay, 0, days)
	for i := days - 1; i >= 0; i-- {
		d := time.Now().AddDate(0, 0, -i)
		key := d.Format("2006-01-02")
		n := counts[key]
		height := int64(3)
		if maxClicks > 0 {
			height = 8 + (n * 110 / maxClicks)
		}
		out = append(out, adminDay{Day: d.Format("01/02"), Clicks: n, Height: height})
	}
	return out
}

func (a *app) adminLogout(w http.ResponseWriter, r *http.Request) {
	http.SetCookie(w, &http.Cookie{Name: adminCookieName, Value: "", Path: "/", MaxAge: -1, HttpOnly: true, SameSite: http.SameSiteStrictMode})
	http.Redirect(w, r, "/admin/login", http.StatusFound)
}

func (a *app) adminSaveLink(w http.ResponseWriter, r *http.Request) {
	if !a.requireAdminPost(w, r) {
		return
	}
	id, _ := strconv.ParseInt(r.FormValue("id"), 10, 64)
	target := normalizeURL(r.FormValue("target_url"))
	if id <= 0 || !allowedURL(target) {
		http.Error(w, "invalid link", http.StatusBadRequest)
		return
	}
	expiresRaw := strings.TrimSpace(r.FormValue("expires_at"))
	var expires any
	if expiresRaw != "" {
		t, err := time.Parse("2006-01-02 15:04:05", expiresRaw)
		if err != nil {
			http.Error(w, "invalid expiry; use YYYY-MM-DD HH:MM:SS", http.StatusBadRequest)
			return
		}
		expires = t
	}
	if db := a.dbRef(); db != nil {
		_, err := db.Exec("UPDATE links SET target_url=?,expires_at=?,updated_at=NOW() WHERE id=?", target, expires, id)
		if err != nil {
			http.Error(w, "failed to save link", http.StatusInternalServerError)
			return
		}
	}
	http.Redirect(w, r, "/admin/?view=links&ok=Link+saved", http.StatusFound)
}

func (a *app) adminToggle(w http.ResponseWriter, r *http.Request) {
	if !a.requireAdminPost(w, r) {
		return
	}
	id, _ := strconv.ParseInt(r.FormValue("id"), 10, 64)
	if db := a.dbRef(); db != nil {
		_, _ = db.Exec("UPDATE links SET enabled=IF(enabled=1,0,1),updated_at=NOW() WHERE id=?", id)
	}
	http.Redirect(w, r, "/admin/?view=links&ok=Link+updated", http.StatusFound)
}

func (a *app) adminDelete(w http.ResponseWriter, r *http.Request) {
	if !a.requireAdminPost(w, r) {
		return
	}
	id, _ := strconv.ParseInt(r.FormValue("id"), 10, 64)
	if db := a.dbRef(); db != nil {
		_, _ = db.Exec("DELETE FROM links WHERE id=?", id)
	}
	http.Redirect(w, r, "/admin/?view=links&ok=Link+deleted", http.StatusFound)
}

func (a *app) adminSettings(w http.ResponseWriter, r *http.Request) {
	if !a.requireAdminPost(w, r) {
		return
	}
	cfg := a.store.get()
	base := strings.TrimRight(strings.TrimSpace(r.FormValue("base_url")), "/")
	if !allowedURL(base) {
		http.Error(w, "invalid base URL", http.StatusBadRequest)
		return
	}
	minLen, _ := strconv.Atoi(r.FormValue("min_length"))
	maxLen, _ := strconv.Atoi(r.FormValue("max_length"))
	if minLen < 1 || maxLen < minLen || maxLen > hardMaxLength {
		http.Error(w, "invalid length range", http.StatusBadRequest)
		return
	}
	cfg.BaseURL = base
	cfg.MinLength = minLen
	cfg.MaxLength = maxLen
	cfg.CreatePerMinute, _ = strconv.Atoi(r.FormValue("create_per_minute"))
	cfg.APIPerMinute, _ = strconv.Atoi(r.FormValue("api_per_minute"))
	if err := a.store.persist(cfg); err != nil {
		http.Error(w, "failed to save settings", http.StatusInternalServerError)
		return
	}
	a.applyConfig(cfg)
	http.Redirect(w, r, "/admin/?view=settings&ok=Settings+saved", http.StatusFound)
}

func (a *app) adminRotateToken(w http.ResponseWriter, r *http.Request) {
	if !a.requireAdminPost(w, r) {
		return
	}
	cfg := a.store.get()
	cfg.APIToken = randomHex(32)
	if cfg.APIToken == "" || a.store.persist(cfg) != nil {
		http.Error(w, "failed to rotate token", http.StatusInternalServerError)
		return
	}
	a.applyConfig(cfg)
	http.Redirect(w, r, "/admin/?view=settings&ok=API+token+rotated", http.StatusFound)
}

func (a *app) adminPassword(w http.ResponseWriter, r *http.Request) {
	if !a.requireAdminPost(w, r) {
		return
	}
	cfg := a.store.get()
	if !passwordOK(cfg.AdminPasswordHash, r.FormValue("current_password")) {
		http.Error(w, "current password is incorrect", http.StatusBadRequest)
		return
	}
	hash, err := hashPassword(r.FormValue("new_password"))
	if err != nil {
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}
	cfg.AdminPasswordHash = hash
	if err := a.store.persist(cfg); err != nil {
		http.Error(w, "failed to save password", http.StatusInternalServerError)
		return
	}
	http.Redirect(w, r, "/admin/?view=settings&ok=Password+updated", http.StatusFound)
}

func (a *app) requireAdminPost(w http.ResponseWriter, r *http.Request) bool {
	if r.Method != http.MethodPost || !a.isAdmin(r) {
		http.Error(w, "unauthorized", http.StatusUnauthorized)
		return false
	}
	_ = r.ParseForm()
	if !hmac.Equal([]byte(a.csrfToken(r)), []byte(r.FormValue("_csrf"))) {
		http.Error(w, "invalid CSRF token", http.StatusBadRequest)
		return false
	}
	return true
}

func (a *app) setAdminSession(w http.ResponseWriter, r *http.Request) {
	cfg := a.store.get()
	exp := time.Now().Add(12 * time.Hour).Unix()
	payload := strconv.FormatInt(exp, 10)
	value := payload + "." + signValue(cfg.SessionSecret, payload)
	http.SetCookie(w, &http.Cookie{
		Name: adminCookieName, Value: value, Path: "/", Expires: time.Unix(exp, 0),
		HttpOnly: true, Secure: requestHTTPS(r), SameSite: http.SameSiteStrictMode,
	})
}

func (a *app) isAdmin(r *http.Request) bool {
	c, err := r.Cookie(adminCookieName)
	if err != nil {
		return false
	}
	parts := strings.Split(c.Value, ".")
	if len(parts) != 2 {
		return false
	}
	exp, err := strconv.ParseInt(parts[0], 10, 64)
	if err != nil || exp < time.Now().Unix() {
		return false
	}
	cfg := a.store.get()
	want := signValue(cfg.SessionSecret, parts[0])
	return hmac.Equal([]byte(want), []byte(parts[1]))
}

func (a *app) csrfToken(r *http.Request) string {
	c, err := r.Cookie(adminCookieName)
	if err != nil {
		return ""
	}
	cfg := a.store.get()
	return signValue(cfg.SessionSecret, "csrf:"+c.Value)
}

func signValue(secret, value string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte(value))
	return hex.EncodeToString(mac.Sum(nil))
}

func requestHTTPS(r *http.Request) bool {
	return r.TLS != nil || strings.EqualFold(strings.TrimSpace(r.Header.Get("X-Forwarded-Proto")), "https")
}

func maskToken(token string) string {
	if len(token) <= 12 {
		if token == "" {
			return "(not configured)"
		}
		return "••••••••"
	}
	return token[:6] + strings.Repeat("•", 20) + token[len(token)-6:]
}

func (a *app) adminExportLinks(w http.ResponseWriter, r *http.Request) {
	if !a.isAdmin(r) {
		http.Error(w, "unauthorized", http.StatusUnauthorized)
		return
	}
	db := a.dbRef()
	if db == nil {
		http.Error(w, "database unavailable", http.StatusServiceUnavailable)
		return
	}
	rows, err := db.Query("SELECT id,e_length,target_url,created_ip,clicks,enabled,created_at,expires_at FROM links ORDER BY id DESC")
	if err != nil {
		http.Error(w, "export failed", http.StatusInternalServerError)
		return
	}
	defer rows.Close()
	w.Header().Set("Content-Type", "text/csv; charset=utf-8")
	w.Header().Set("Content-Disposition", "attachment; filename=eeee-links.csv")
	_, _ = w.Write([]byte{0xEF, 0xBB, 0xBF})
	cw := csv.NewWriter(w)
	_ = cw.Write([]string{"id", "e_length", "target_url", "created_ip", "clicks", "enabled", "created_at", "expires_at"})
	for rows.Next() {
		var id, length, clicks int64
		var target, createdIP string
		var enabled bool
		var created time.Time
		var expires *time.Time
		if rows.Scan(&id, &length, &target, &createdIP, &clicks, &enabled, &created, &expires) != nil {
			continue
		}
		exp := ""
		if expires != nil {
			exp = expires.Format("2006-01-02 15:04:05")
		}
		_ = cw.Write([]string{strconv.FormatInt(id, 10), strconv.FormatInt(length, 10), csvSafe(target), createdIP, strconv.FormatInt(clicks, 10), strconv.FormatBool(enabled), created.Format("2006-01-02 15:04:05"), exp})
	}
	cw.Flush()
}

func (a *app) adminExportClicks(w http.ResponseWriter, r *http.Request) {
	if !a.isAdmin(r) {
		http.Error(w, "unauthorized", http.StatusUnauthorized)
		return
	}
	db := a.dbRef()
	if db == nil {
		http.Error(w, "database unavailable", http.StatusServiceUnavailable)
		return
	}
	rows, err := db.Query("SELECT c.id,l.e_length,c.clicked_at,c.ip,c.user_agent,c.referer,c.request_uri FROM click_logs c JOIN links l ON l.id=c.link_id ORDER BY c.id DESC LIMIT 50000")
	if err != nil {
		http.Error(w, "export failed", http.StatusInternalServerError)
		return
	}
	defer rows.Close()
	w.Header().Set("Content-Type", "text/csv; charset=utf-8")
	w.Header().Set("Content-Disposition", "attachment; filename=eeee-clicks.csv")
	_, _ = w.Write([]byte{0xEF, 0xBB, 0xBF})
	cw := csv.NewWriter(w)
	_ = cw.Write([]string{"id", "e_length", "clicked_at", "ip", "user_agent", "referer", "request_uri"})
	for rows.Next() {
		var id, length int64
		var clicked time.Time
		var ip, ua, ref, uri string
		if rows.Scan(&id, &length, &clicked, &ip, &ua, &ref, &uri) != nil {
			continue
		}
		_ = cw.Write([]string{strconv.FormatInt(id, 10), strconv.FormatInt(length, 10), clicked.Format("2006-01-02 15:04:05"), csvSafe(ip), csvSafe(ua), csvSafe(ref), csvSafe(uri)})
	}
	cw.Flush()
}

func csvSafe(s string) string {
	if s == "" {
		return s
	}
	switch s[0] {
	case '=', '+', '-', '@':
		return "'" + s
	default:
		return s
	}
}
