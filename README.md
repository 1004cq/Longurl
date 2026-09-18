# EEEE Long URL

[English](README.md) · [中文](README.zh-CN.md)

Make URLs longer on purpose. This is **not** a URL shortener.

The generated path contains **only the letter `e`**. The number of `e` characters is the unique ID. No random letters or digits.

```
Original:  https://example.com/test
Generated: https://your-domain.example/eeeeeeeeee
```

That path has 10 `e` characters, so the row in the database is `e_length = 10`.  
If 10 is taken, the app tries 11, 12, … until it finds a free length.

When someone opens a pure-`e` path:

- Found and enabled → `302` to the target URL, click recorded
- Missing / disabled / expired → custom 404 (“This e is lost.”)
- Any non-`e` character in the path → also 404

---

## Features

- Home page generator: paste a URL, pick or type a length (8–5000; shortcuts 50 / 100 / 200 / 500 / 1000 / 2000)
- Live preview of final URL length
- Copy / Open; Enter to submit; loading and error states
- Auto-prefix `https://`; only public `http` / `https` targets
- Admin V2: responsive English/中文 UI, dashboard, search, pagination, edit target/expiry, enable/disable/delete, visit logs, API and settings
- Analytics: 7-day / 30-day traffic, Top Links, recent visits, CSV exports\n- API: `POST /api/create` with header token authentication
- Web installer at `/install/`, locked after setup

---

## Stack

| Item | Requirement |
|---|---|
| Language | PHP 8.3 / 8.4 / 8.5 (`strict_types`) |
| Database | MySQL 5.7+ / 8.x, InnoDB, utf8mb4 |
| Web server | Nginx (aaPanel / BT Panel friendly) |
| Dependencies | No Node.js, no Composer |
| PHP extensions | `pdo` `pdo_mysql` `mbstring` `openssl` `json` `session` `filter` |

---

## Layout

```
.
├── config/
│   └── local.php.example    # sample; real local.php is created by the installer and is gitignored
├── public/                 # web root (Nginx root)
│   ├── index.php             # front door: home / redirect / API
│   ├── admin/                # admin UI
│   ├── install/              # installer
│   └── assets/               # CSS / JS
├── src/                    # application code
│   ├── bootstrap.php
│   ├── Database.php
│   ├── LinkService.php
│   ├── Auth.php
│   ├── RateLimiter.php
│   ├── Helpers.php
│   └── views/
├── sql/schema.sql          # tables
├── nginx/rewrite.conf      # Nginx / aaPanel rewrite
├── storage/                # installed.lock after setup
├── LICENSE                 # MIT
├── README.md               # English
└── README.zh-CN.md         # Chinese
```

Suggested paths on the server:

```
/www/wwwroot/YOUR_DOMAIN/e/          # project root
/www/wwwroot/YOUR_DOMAIN/e/public    # website document root
```

Keep `config/` and `src/` outside the document root. Never put secrets under `public/`.

---

## Install

### 1. Upload the code

Place the repo under the project directory, for example `/www/wwwroot/YOUR_DOMAIN/e/`.

### 2. Create the database

```sql
CREATE DATABASE eeee_longurl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eeee_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON eeee_longurl.* TO 'eeee_user'@'localhost';
FLUSH PRIVILEGES;
```

Replace `CHANGE_ME`. The installer imports `sql/schema.sql`; you usually do not import it by hand.

Tables:

- `links` — e length, target URL, clicks, enabled flag, timestamps
- `click_logs` — IP, user agent, referer, URI, time
- `rate_limits` — create / API / login throttling

### 3. Permissions

On aaPanel the PHP user is usually `www`:

```bash
chown -R www:www /www/wwwroot/YOUR_DOMAIN/e
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/config
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/storage
```

PHP must be able to write:

- `config/local.php` (created at install)
- `storage/installed.lock` (locks the installer)

### 4. Website in aaPanel / Nginx

1. Create the site. Set the **document root** to `.../e/public`, not the project root.
2. Choose PHP 8.x.
3. Paste `nginx/rewrite.conf` into the site rewrite / Nginx config.
4. Reload Nginx.

Change `fastcgi_pass` in `nginx/rewrite.conf` to the socket for your PHP version. Common aaPanel values:

```
unix:/tmp/php-cgi-85.sock
unix:/tmp/php-cgi-84.sock
unix:/tmp/php-cgi-80.sock
```

Very long paths (1000+ `e`) need larger header buffers. The sample config already has:

```nginx
large_client_header_buffers 8 32k;
client_header_buffer_size 16k;
```

### 5. Web installer

Open:

```
https://YOUR_DOMAIN/install/
```

Fields:

| Field | Meaning |
|---|---|
| DB host / port | Usually `127.0.0.1` and `3306` |
| DB name / user / password | The database from step 2 |
| Website URL | Public base URL with `https://`, no trailing slash |
| Admin password | Stored with `password_hash` |

The installer will:

1. Test the database connection
2. Import tables
3. Write `config/local.php`
4. Generate an API token
5. Write `storage/installed.lock` and disable itself

Then sign in at `/admin/`.

The API token lives only in `config/local.php` on the server. **Do not commit that file.**

---

## API

```
POST /api/create
```

Authenticate with any one of:

- Header: `Authorization: Bearer YOUR_TOKEN`
- Header: `X-API-Token: YOUR_TOKEN`
- Form field: `token`

Parameters:

| Field | Meaning |
|---|---|
| `url` | Target URL |
| `length` | Desired number of `e` characters |

Success:

```json
{
  "success": true,
  "url": "https://your-domain.example/eeeeeeeeee",
  "length": 10,
  "target": "https://example.com"
}
```

On failure `success` is `false`. HTTP status may be 400, 401, or 429.

---

## Admin

URL: `/admin/`

- Dashboard — link count, total clicks, clicks today, recent links, recent visits
- Links — search, enable, disable, delete
- Analytics — IP, UA, referer, URI, time
- API / Settings — notes only; rotate the token or password in `config/local.php` on the server

---

## Security

- PDO prepared statements
- `htmlspecialchars` on output
- URLs validated with `FILTER_VALIDATE_URL`; only `http`/`https`; no `javascript:`, `data:`, `file:`, private IPs, or localhost
- CSRF on forms; session login
- Cookies: httponly, secure on HTTPS, SameSite=Lax
- Per-minute limits on create, API, and admin login
- Detailed PHP errors off in production; DB errors never print credentials
- Admin password stored as a hash only

---

## Troubleshooting

| Symptom | What to check |
|---|---|
| Always redirected to `/install/` | Missing `config/local.php` or `storage/installed.lock`; write permissions |
| Install failed | Wrong DB credentials, or `config` / `storage` not writable |
| HTTP 500 | PHP error log; keep `display_errors` off in production |
| Long path returns 400 / 414 | Raise Nginx header buffers; see `nginx/rewrite.conf` |
| Redirect 404 | Path must be only `e`, or that length is unused / disabled |
| Cannot sign in | Wrong password, or more than 8 attempts per minute per IP |
| 502 from PHP | `fastcgi_pass` socket does not match the selected PHP version |
| Rewrite not working | Document root must be `public`; rules must be on this site |

---

## License

MIT License. See [LICENSE](LICENSE).
