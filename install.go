package main

import (
	"database/sql"
	"fmt"
	"html/template"
	"io/fs"
	"net/http"
	"regexp"
	"strings"

	_ "github.com/go-sql-driver/mysql"
)

var installTemplates = template.Must(template.ParseFS(webFS, "templates/install.html"))
var safeDBIdent = regexp.MustCompile("^[A-Za-z0-9_]+$")

type installPageData struct {
	Token   string
	Error   string
	Success bool
	DBHost  string
	DBPort  string
	DBName  string
	DBUser  string
	BaseURL string
}

func (a *app) installPage(w http.ResponseWriter, r *http.Request) {
	if a.installed() {
		http.Redirect(w, r, "/admin/", http.StatusFound)
		return
	}
	cfg := a.store.get()
	data := installPageData{
		Token: a.installToken,
		DBHost: cfg.DBHost,
		DBPort: cfg.DBPort,
		DBName: cfg.DBName,
		DBUser: cfg.DBUser,
		BaseURL: cfg.BaseURL,
	}

	if r.Method == http.MethodPost {
		_ = r.ParseForm()
		if r.FormValue("_token") == "" || r.FormValue("_token") != a.installToken {
			data.Error = "Invalid installer token. Refresh and try again."
			a.renderInstall(w, data)
			return
		}

		cfg.DBHost = strings.TrimSpace(r.FormValue("db_host"))
		cfg.DBPort = strings.TrimSpace(r.FormValue("db_port"))
		cfg.DBName = strings.TrimSpace(r.FormValue("db_name"))
		cfg.DBUser = strings.TrimSpace(r.FormValue("db_user"))
		cfg.DBPass = r.FormValue("db_pass")
		cfg.BaseURL = strings.TrimRight(strings.TrimSpace(r.FormValue("base_url")), "/")
		data.DBHost, data.DBPort, data.DBName, data.DBUser, data.BaseURL = cfg.DBHost, cfg.DBPort, cfg.DBName, cfg.DBUser, cfg.BaseURL

		if !safeDBIdent.MatchString(cfg.DBName) {
			data.Error = "Database name may contain only letters, numbers and underscore."
			a.renderInstall(w, data)
			return
		}
		if cfg.DBHost == "" || cfg.DBPort == "" || cfg.DBUser == "" || cfg.DBPass == "" {
			data.Error = "Database fields are required."
			a.renderInstall(w, data)
			return
		}
		if !allowedURL(cfg.BaseURL) {
			data.Error = "Website URL must be a valid public http/https URL."
			a.renderInstall(w, data)
			return
		}

		hash, err := hashPassword(r.FormValue("admin_password"))
		if err != nil {
			data.Error = err.Error()
			a.renderInstall(w, data)
			return
		}
		cfg.AdminPasswordHash = hash
		cfg.APIToken = randomHex(32)
		cfg.SessionSecret = randomHex(32)
		if cfg.APIToken == "" || cfg.SessionSecret == "" {
			data.Error = "Failed to generate application secrets."
			a.renderInstall(w, data)
			return
		}

		db := openDatabase(cfg)
		if db == nil {
			data.Error = "Could not create database connection."
			a.renderInstall(w, data)
			return
		}
		if err := db.Ping(); err != nil {
			_ = db.Close()
			data.Error = "Database connection failed: " + err.Error()
			a.renderInstall(w, data)
			return
		}
		if err := importSchema(db); err != nil {
			_ = db.Close()
			data.Error = "Schema import failed: " + err.Error()
			a.renderInstall(w, data)
			return
		}
		if err := a.store.persist(cfg); err != nil {
			_ = db.Close()
			data.Error = "Could not write .env: " + err.Error()
			a.renderInstall(w, data)
			return
		}

		a.replaceDB(db)
		a.applyConfig(cfg)
		data.Success = true
		data.Error = ""
		a.renderInstall(w, data)
		return
	}

	a.renderInstall(w, data)
}

func (a *app) renderInstall(w http.ResponseWriter, data installPageData) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := installTemplates.ExecuteTemplate(w, "install.html", data); err != nil {
		http.Error(w, "template error", http.StatusInternalServerError)
	}
}

func importSchema(db *sql.DB) error {
	content, err := fs.ReadFile(webFS, "sql/schema.sql")
	if err != nil {
		return err
	}
	for _, stmt := range strings.Split(string(content), ";") {
		stmt = strings.TrimSpace(stmt)
		if stmt == "" {
			continue
		}
		if _, err := db.Exec(stmt); err != nil {
			return fmt.Errorf("%w", err)
		}
	}
	return nil
}
