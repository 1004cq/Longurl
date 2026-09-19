#!/usr/bin/env python3
"""Minimal production-oriented EEEE backend using stdlib HTTP server and SQLite."""
from __future__ import annotations
import base64, hashlib, json, os, re, secrets, sqlite3, time
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

BASE_URL = os.getenv("APP_BASE_URL", "http://localhost:8080").rstrip("/")
DB_URL = os.getenv("DATABASE_URL", "sqlite:///./data/eeee.db")
DB_PATH = DB_URL.removeprefix("sqlite:///") if DB_URL.startswith("sqlite:///") else "./data/eeee.db"
API_TOKEN = os.getenv("API_TOKEN", "")
MIN_LENGTH = int(os.getenv("MIN_LENGTH", "8"))
MAX_LENGTH = int(os.getenv("MAX_LENGTH", "5000"))
RATE_LIMIT = int(os.getenv("RATE_LIMIT_PER_MINUTE", "20"))
PORT = int(os.getenv("PORT", "8080"))

SCHEMA = """
CREATE TABLE IF NOT EXISTS links (id INTEGER PRIMARY KEY AUTOINCREMENT, e_length INTEGER NOT NULL UNIQUE, target_url TEXT NOT NULL, clicks INTEGER NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, expires_at TEXT);
CREATE TABLE IF NOT EXISTS click_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, link_id INTEGER NOT NULL, clicked_at TEXT NOT NULL, ip TEXT NOT NULL DEFAULT '', user_agent TEXT NOT NULL DEFAULT '', referer TEXT NOT NULL DEFAULT '', request_uri TEXT NOT NULL DEFAULT '', FOREIGN KEY(link_id) REFERENCES links(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS rate_limits (bucket TEXT NOT NULL, ip TEXT NOT NULL, window_start INTEGER NOT NULL, hits INTEGER NOT NULL DEFAULT 1, PRIMARY KEY(bucket, ip, window_start));
"""

def now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")

def db() -> sqlite3.Connection:
    parent = os.path.dirname(os.path.abspath(DB_PATH))
    os.makedirs(parent, exist_ok=True)
    conn = sqlite3.connect(DB_PATH, timeout=10)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys=ON")
    conn.executescript(SCHEMA)
    return conn

def public_url(url: str) -> bool:
    try:
        parsed = urlparse(url)
        if parsed.scheme not in ("http", "https") or not parsed.hostname:
            return False
        host = parsed.hostname.lower()
        if host == "localhost" or host.endswith(".local"):
            return False
        if host.replace(".", "").isdigit():
            parts = [int(x) for x in host.split(".")]
            if len(parts) == 4 and (parts[0] == 10 or parts[0] == 127 or parts[0] == 0 or parts[0] == 192 and parts[1] == 168 or parts[0] == 172 and 16 <= parts[1] <= 31):
                return False
        return True
    except ValueError:
        return False

def normalize_url(value: str) -> str:
    value = value.strip()
    return value if re.match(r"^[a-z][a-z0-9+.-]*://", value, re.I) else "https://" + value

def allocate(conn: sqlite3.Connection, wanted: int) -> int:
    wanted = max(MIN_LENGTH, min(MAX_LENGTH, wanted))
    rows = {int(r[0]) for r in conn.execute("SELECT e_length FROM links WHERE e_length BETWEEN ? AND ?", (MIN_LENGTH, MAX_LENGTH))}
    for radius in (0, 4, 12, 32, 64, 128, 256):
        candidates = [n for n in range(max(MIN_LENGTH, wanted-radius), min(MAX_LENGTH, wanted+radius)+1) if n not in rows]
        if candidates:
            return secrets.choice(sorted(candidates, key=lambda n: abs(n-wanted))[:max(4, min(len(candidates), radius+1))])
    for n in range(wanted, MAX_LENGTH + 1):
        if n not in rows: return n
    raise ValueError("No free e-length available in the allowed range.")

def create_link(url: str, wanted: int) -> dict:
    url = normalize_url(url)
    if not public_url(url): raise ValueError("Invalid URL. Only http/https public URLs are allowed.")
    if wanted < MIN_LENGTH or wanted > MAX_LENGTH: raise ValueError(f"Length must be between {MIN_LENGTH} and {MAX_LENGTH}.")
    conn = db()
    try:
        for _ in range(8):
            length = allocate(conn, wanted)
            try:
                stamp = now(); cur = conn.execute("INSERT INTO links(e_length,target_url,created_at,updated_at) VALUES(?,?,?,?)", (length,url,stamp,stamp)); conn.commit()
                return {"id":cur.lastrowid,"length":length,"target":url,"url":f"{BASE_URL}/" + "e" * length}
            except sqlite3.IntegrityError: conn.rollback()
        raise RuntimeError("Could not allocate a free length.")
    finally: conn.close()

def json_bytes(payload: dict) -> bytes:
    return json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode()

class Handler(BaseHTTPRequestHandler):
    server_version = "EEEE-Python/1.0"
    def log_message(self, *_): pass
    def send_json(self, payload, status=200):
        body=json_bytes(payload); self.send_response(status); self.send_header("Content-Type","application/json; charset=utf-8"); self.send_header("Cache-Control","no-store"); self.send_header("Content-Length",str(len(body))); self.end_headers(); self.wfile.write(body)
    def read_data(self):
        length=int(self.headers.get("Content-Length", "0")); raw=self.rfile.read(length) if length else b""
        if self.headers.get("Content-Type", "").startswith("application/json"): return json.loads(raw or b"{}")
        return {k:v[-1] for k,v in parse_qs(raw.decode()).items()}
    def authorized(self, data):
        token=self.headers.get("X-API-Token", "") or self.headers.get("Authorization", "").removeprefix("Bearer ").strip() or str(data.get("token", ""))
        return bool(API_TOKEN) and secrets.compare_digest(token, API_TOKEN)
    def do_GET(self):
        path=urlparse(self.path).path
        if path == "/healthz": return self.send_json({"ok":True,"service":"eeee-python"})
        if path == "/": return self.send_json({"service":"EEEE Long URL","implementation":"python","status":"ok"})
        if re.fullmatch(r"/e+", path):
            length=len(path)-1; conn=db(); row=conn.execute("SELECT * FROM links WHERE e_length=? AND enabled=1",(length,)).fetchone()
            if row and (not row["expires_at"] or row["expires_at"] >= now()):
                stamp=now(); conn.execute("UPDATE links SET clicks=clicks+1,updated_at=? WHERE id=?",(stamp,row["id"])); conn.execute("INSERT INTO click_logs(link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES(?,?,?,?,?,?)",(row["id"],stamp,self.client_address[0],self.headers.get("User-Agent","")[:512],self.headers.get("Referer","")[:1024],self.path[:2048])); conn.commit(); conn.close(); self.send_response(302); self.send_header("Location",row["target_url"]); self.end_headers(); return
            conn.close()
        self.send_json({"success":False,"error":"Not found"},404)
    def do_POST(self):
        if urlparse(self.path).path != "/api/create": return self.send_json({"success":False,"error":"Not found"},404)
        try:
            data=self.read_data()
            if not self.authorized(data): return self.send_json({"success":False,"error":"Unauthorized"},401)
            result=create_link(str(data.get("url","")),int(data.get("length",50))); self.send_json({"success":True,**result})
        except ValueError as exc: self.send_json({"success":False,"error":str(exc)},400)
        except Exception: self.send_json({"success":False,"error":"Unable to create link right now."},500)

if __name__ == "__main__":
    db().close(); print(f"EEEE Python backend listening on :{PORT}"); ThreadingHTTPServer(("0.0.0.0",PORT),Handler).serve_forever()
