package main

import (
	"bufio"
	"crypto/rand"
	"encoding/hex"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"

	"golang.org/x/crypto/bcrypt"
)

type runtimeConfig struct {
	DBHost           string
	DBPort           string
	DBName           string
	DBUser           string
	DBPass           string
	BaseURL          string
	APIToken         string
	AdminPasswordHash string
	SessionSecret    string
	MinLength        int
	MaxLength        int
	CreatePerMinute  int
	APIPerMinute     int
	Port             string
}

type configStore struct {
	mu   sync.RWMutex
	path string
	cfg  runtimeConfig
}

func loadDotEnv(path string) map[string]string {
	out := map[string]string{}
	f, err := os.Open(path)
	if err != nil {
		return out
	}
	defer f.Close()

	sc := bufio.NewScanner(f)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		k, v, ok := strings.Cut(line, "=")
		if !ok {
			continue
		}
		k = strings.TrimSpace(k)
		v = strings.TrimSpace(v)
		if len(v) >= 2 && ((v[0] == '"' && v[len(v)-1] == '"') || (v[0] == '\'' && v[len(v)-1] == '\'')) {
			v = v[1 : len(v)-1]
		}
		out[k] = v
	}
	return out
}

func cfgValue(file map[string]string, key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	if v := file[key]; v != "" {
		return v
	}
	return fallback
}

func cfgInt(file map[string]string, key string, fallback int) int {
	v, err := strconv.Atoi(cfgValue(file, key, strconv.Itoa(fallback)))
	if err != nil {
		return fallback
	}
	return v
}

func loadRuntimeConfig(path string) runtimeConfig {
	file := loadDotEnv(path)
	return runtimeConfig{
		DBHost:            cfgValue(file, "DB_HOST", "127.0.0.1"),
		DBPort:            cfgValue(file, "DB_PORT", "3306"),
		DBName:            cfgValue(file, "DB_NAME", "admin"),
		DBUser:            cfgValue(file, "DB_USER", "admin"),
		DBPass:            cfgValue(file, "DB_PASS", ""),
		BaseURL:           strings.TrimRight(cfgValue(file, "BASE_URL", "http://127.0.0.1:8080"), "/"),
		APIToken:          cfgValue(file, "API_TOKEN", ""),
		AdminPasswordHash: cfgValue(file, "ADMIN_PASSWORD_HASH", ""),
		SessionSecret:     cfgValue(file, "SESSION_SECRET", ""),
		MinLength:         cfgInt(file, "MIN_LENGTH", 8),
		MaxLength:         cfgInt(file, "MAX_LENGTH", 5000),
		CreatePerMinute:   cfgInt(file, "CREATE_PER_MINUTE", 20),
		APIPerMinute:      cfgInt(file, "API_PER_MINUTE", 60),
		Port:              cfgValue(file, "PORT", "8080"),
	}
}

func newConfigStore(path string) *configStore {
	return &configStore{path: path, cfg: loadRuntimeConfig(path)}
}

func (s *configStore) get() runtimeConfig {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.cfg
}

func (s *configStore) set(cfg runtimeConfig) {
	s.mu.Lock()
	s.cfg = cfg
	s.mu.Unlock()
}

func (s *configStore) persist(cfg runtimeConfig) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	dir := filepath.Dir(s.path)
	if dir != "." {
		if err := os.MkdirAll(dir, 0750); err != nil {
			return err
		}
	}

	content := strings.Join([]string{
		"DB_HOST=" + envLine(cfg.DBHost),
		"DB_PORT=" + envLine(cfg.DBPort),
		"DB_NAME=" + envLine(cfg.DBName),
		"DB_USER=" + envLine(cfg.DBUser),
		"DB_PASS=" + envLine(cfg.DBPass),
		"",
		"BASE_URL=" + envLine(cfg.BaseURL),
		"API_TOKEN=" + envLine(cfg.APIToken),
		"ADMIN_PASSWORD_HASH=" + envLine(cfg.AdminPasswordHash),
		"SESSION_SECRET=" + envLine(cfg.SessionSecret),
		"MIN_LENGTH=" + strconv.Itoa(cfg.MinLength),
		"MAX_LENGTH=" + strconv.Itoa(cfg.MaxLength),
		"CREATE_PER_MINUTE=" + strconv.Itoa(cfg.CreatePerMinute),
		"API_PER_MINUTE=" + strconv.Itoa(cfg.APIPerMinute),
		"PORT=" + envLine(cfg.Port),
		"",
	}, "\n")

	tmp := s.path + ".tmp"
	if err := os.WriteFile(tmp, []byte(content), 0640); err != nil {
		return err
	}
	if err := os.Rename(tmp, s.path); err != nil {
		return err
	}
	s.cfg = cfg
	return nil
}

func envLine(v string) string {
	if v == "" {
		return ""
	}
	if strings.ContainsAny(v, " #\t\r\n\"'") {
		return strconv.Quote(v)
	}
	return v
}

func randomHex(bytes int) string {
	b := make([]byte, bytes)
	if _, err := rand.Read(b); err != nil {
		return ""
	}
	return hex.EncodeToString(b)
}

func hashPassword(password string) (string, error) {
	if len(password) < 10 {
		return "", fmt.Errorf("password must be at least 10 characters")
	}
	h, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	return string(h), err
}

func passwordOK(hash, password string) bool {
	if hash == "" || password == "" {
		return false
	}
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
}
