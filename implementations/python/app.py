import os
import random
import ipaddress
from urllib.parse import urlparse
from flask import Flask, request, jsonify, redirect, abort
import pymysql

app = Flask(__name__)

BASE_URL = os.getenv("BASE_URL", "http://127.0.0.1:8080").rstrip("/")
API_TOKEN = os.getenv("API_TOKEN", "")
MIN_LENGTH = int(os.getenv("MIN_LENGTH", "8"))
MAX_LENGTH = int(os.getenv("MAX_LENGTH", "5000"))
RADII = (0, 4, 12, 32, 64, 128, 256)


def db():
    return pymysql.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USER", "eeee_user"),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "eeee_longurl"),
        charset="utf8mb4",
        autocommit=False,
        cursorclass=pymysql.cursors.DictCursor,
    )


def normalize_url(value: str) -> str:
    value = value.strip()
    if value and "://" not in value:
        value = "https://" + value
    return value


def allowed_url(value: str) -> bool:
    try:
        u = urlparse(value)
        if u.scheme not in ("http", "https") or not u.hostname:
            return False
        host = u.hostname.lower()
        if host == "localhost" or host.endswith(".local"):
            return False
        try:
            ip = ipaddress.ip_address(host)
            if ip.is_private or ip.is_loopback or ip.is_reserved or ip.is_unspecified:
                return False
        except ValueError:
            pass
        return True
    except Exception:
        return False


def authorized() -> bool:
    if not API_TOKEN:
        return False
    auth = request.headers.get("Authorization", "").strip()
    if auth.lower().startswith("bearer ") and auth[7:].strip() == API_TOKEN:
        return True
    if request.headers.get("X-API-Token") == API_TOKEN:
        return True
    return request.form.get("token") == API_TOKEN or (request.json or {}).get("token") == API_TOKEN


def client_ip() -> str:
    xff = request.headers.get("X-Forwarded-For", "")
    if xff:
        return xff.split(",")[0].strip()
    real = request.headers.get("X-Real-IP", "").strip()
    return real or (request.remote_addr or "")


def used_in_range(cur, low: int, high: int) -> set[int]:
    cur.execute("SELECT e_length FROM links WHERE e_length>=%s AND e_length<=%s", (low, high))
    return {int(row["e_length"]) for row in cur.fetchall()}


def pick_nearby(cur, wanted: int, tried: set[int]) -> int | None:
    for radius in RADII:
        low = max(MIN_LENGTH, wanted - radius)
        high = min(MAX_LENGTH, wanted + radius)
        used = used_in_range(cur, low, high)
        free = [n for n in range(low, high + 1) if n not in used and n not in tried]
        if not free:
            continue
        free.sort(key=lambda n: (abs(n - wanted), n))
        pool = free[: max(4, radius + 1)]
        return random.choice(pool)
    for n in range(wanted, MAX_LENGTH + 1):
        if n not in tried:
            return n
    return None


@app.get("/healthz")
def healthz():
    conn = None
    try:
        conn = db()
        with conn.cursor() as cur:
            cur.execute("SELECT 1")
            cur.fetchone()
        return jsonify(ok=True, db=True)
    except Exception:
        return jsonify(ok=False, db=False), 503
    finally:
        if conn is not None:
            conn.close()


@app.post("/api/create")
def create():
    if not authorized():
        return jsonify(success=False, error="Unauthorized"), 401

    payload = request.form if request.form else (request.json or {})
    target = normalize_url(str(payload.get("url", "")))
    try:
        wanted = int(payload.get("length", 100))
    except (TypeError, ValueError):
        wanted = 100

    if not allowed_url(target):
        return jsonify(success=False, error="Invalid URL"), 400
    if wanted < MIN_LENGTH or wanted > MAX_LENGTH:
        return jsonify(success=False, error="Invalid length"), 400

    conn = db()
    tried: set[int] = set()
    try:
        with conn.cursor() as cur:
            for _ in range(24):
                candidate = pick_nearby(cur, wanted, tried)
                if candidate is None:
                    break
                tried.add(candidate)
                try:
                    cur.execute(
                        "INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) "
                        "VALUES (%s,%s,0,1,NOW(),NOW())",
                        (candidate, target),
                    )
                    conn.commit()
                    return jsonify(
                        success=True,
                        id=cur.lastrowid,
                        length=candidate,
                        target=target,
                        url=BASE_URL + "/" + ("e" * candidate),
                    )
                except pymysql.err.IntegrityError as exc:
                    conn.rollback()
                    if exc.args and exc.args[0] == 1062:
                        continue
                    raise
    finally:
        conn.close()

    return jsonify(success=False, error="No free e-length"), 409


@app.get("/")
def home():
    return "EEEE Long URL — Python\n"


@app.get("/<path:path>")
def resolve(path: str):
    if not path or set(path) != {"e"}:
        abort(404)

    conn = db()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=%s LIMIT 1",
                (len(path),),
            )
            row = cur.fetchone()
            if not row or not row["enabled"]:
                abort(404)
            if row["expires_at"] is not None:
                from datetime import datetime
                if row["expires_at"] < datetime.now():
                    abort(404)

            try:
                cur.execute("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=%s", (row["id"],))
                cur.execute(
                    "INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) "
                    "VALUES (%s,NOW(),%s,%s,%s,%s)",
                    (
                        row["id"],
                        client_ip(),
                        (request.user_agent.string or "")[:512],
                        (request.referrer or "")[:1024],
                        request.full_path[:2048],
                    ),
                )
                conn.commit()
            except Exception:
                conn.rollback()
            return redirect(row["target_url"], code=302)
    finally:
        conn.close()


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.getenv("PORT", "8080")))
