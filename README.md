# EEEE Long URL

[English](README.md) · [中文](README.zh-CN.md)

A **long-URL generator**, not a shortener.

Paste a real URL. The app returns a link whose path is **only the letter `e`**.  
The unique key is **how many `e` characters** (`e_length`). There is no random alphanumeric slug.

```
Original:  https://example.com/test
Generated: https://your-domain.example/eeeeeeeeee
```

That path has 10 `e` characters, stored as `e_length = 10`.

This repository does not contain private deployment data. Do not commit real domains, server IPs, database passwords, API tokens, or admin passwords.

---

## How it works

1. The visitor picks a desired length, for example 100.
2. The server looks for an unused `e_length` near 100.
3. Under concurrent use it randomly picks a free nearby value so many users are not serialized onto 100, 101, 102.
4. If the nearby window is full, the window grows; then it scans upward from the target.
5. `e_length` is unique. A colliding insert is rejected and retried.
6. A request to `/` plus a run of `e` looks up that length. If the row is enabled and not expired, the app records a click and returns `302`.
7. Any non-`e` character, or a missing row, yields 404.

Default range is 8–5000. The home page uses a slider, preset ticks (50 / 100 / 200 / 500 / 1000 / 2000), and a number field.

---

## Features

### Public site

- Target URL input; `https://` is added when the scheme is missing
- Slider + ticks + numeric field
- Live preview of the long URL
- Copy / open after create
- English / Chinese switch
- Mobile layout that does not grow from huge `e` strings

### Redirects

- Pure-`e` paths only
- 302 plus click log (IP, UA, referer, URI, time)
- Disabled or expired links return 404

### Admin `/admin/`

- Dashboard counts and recent activity
- Link search, pagination, edit target/expiry, enable, disable, delete
- Analytics, 7/30-day views, top links, CSV export
- API / settings notes; rotate secrets only on the server

### API

`POST /api/create` with a token.

### Installer

`/install/` writes config and then locks itself.

---

## Requirements

| Item | Requirement |
|---|---|
| OS | Ubuntu + Nginx (aaPanel / BT Panel is fine) |
| PHP | 8.3 / 8.4 / 8.5 with `strict_types` |
| Extensions | `pdo` `pdo_mysql` `mbstring` `openssl` `json` `session` `filter` |
| Database | MySQL 5.7+ or 8.x, InnoDB, utf8mb4 |
| Not used | Node.js, Composer |

Website HTTPS needs a **site certificate** (Let’s Encrypt or similar). An SSH key is only for logging into the machine.

---

## Layout

```
.
├── config/local.php.example
├── public/          # document root
├── src/
├── sql/schema.sql
├── nginx/rewrite.conf
├── storage/
├── LICENSE
├── README.md
└── README.zh-CN.md
```

Suggested disk layout (replace `YOUR_DOMAIN` locally; do not put a real hostname in git):

```
/www/wwwroot/YOUR_DOMAIN/e/
/www/wwwroot/YOUR_DOMAIN/e/public
```

Keep `config/` and `src/` outside the document root.

---

## Do not commit

- `config/local.php`
- `storage/installed.lock`
- `.env`, DB passwords, API tokens, private keys
- Real site hostnames, panel ports, public IPs

Only `config/local.php.example` belongs in git.

---

## Install

### 1. Get the code

```bash
cd /www/wwwroot/YOUR_DOMAIN/e
git clone https://github.com/1004cq/Longurl.git .
```

Or unzip a release into that folder so `public/` and `src/` are present.

Update an existing copy:

```bash
cd /www/wwwroot/YOUR_DOMAIN/e
git pull
```

Do not overwrite a working `config/local.php`.

### 2. Database

```sql
CREATE DATABASE eeee_longurl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eeee_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON eeee_longurl.* TO 'eeee_user'@'localhost';
FLUSH PRIVILEGES;
```

Replace `CHANGE_ME` on the server only. The installer imports `sql/schema.sql`.

| Table | Role |
|---|---|
| `links` | length, target, clicks, enabled, expiry |
| `click_logs` | per-redirect metadata |
| `rate_limits` | create / API / login throttles |

### 3. Permissions

```bash
chown -R www:www /www/wwwroot/YOUR_DOMAIN/e
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/config
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/storage
```

PHP must write `config/local.php` and `storage/installed.lock`.

### 4. aaPanel site

1. Add a site.
2. Website directory = project root `.../e`.
3. Running directory = `/public`.
4. PHP 8.x.
5. Paste `nginx/rewrite.conf`.
6. Set `fastcgi_pass` to the socket used by this PHP version.
7. Reload Nginx.

Long paths need larger header buffers; the sample config includes them.

### 5. HTTPS

`ERR_SSL_PROTOCOL_ERROR` usually means port 443 has no site certificate, or `listen 443` is missing `ssl`.

1. Confirm the app on `http://YOUR_DOMAIN/install/` first.
2. Issue Let’s Encrypt from the site SSL page.
3. Do not force HTTPS until the certificate is live.
4. Open 80 and 443 on the cloud firewall.
5. Do not use an SSH key as a web certificate.

### 6. Web installer

Open `/install/` and fill host, port, database name, user, password, public base URL (`https://your-domain.example`, no trailing slash), and admin password.

The installer tests the database, imports tables, writes `config/local.php`, creates an API token, and locks itself. Then use `/admin/`.

---

## Config

See `config/local.php.example`:

- `db.*` — database
- `app.base_url` — public origin used when building long URLs
- `app.min_length` / `app.max_length`
- `admin.password_hash` / `admin.api_token`
- `rate.*` — per-minute limits

Rotate the token on the server. Never paste the live value into git.

---

## API

```
POST /api/create
```

Auth: `Authorization: Bearer TOKEN`, or `X-API-Token`, or form field `token`.

Body: `url`, `length`.

```json
{
  "success": true,
  "url": "https://your-domain.example/eeeeeeeeee",
  "length": 10,
  "target": "https://example.com"
}
```

`length` is the value actually stored and may differ slightly from the request when nearby slots are taken. Errors: 400 / 401 / 429.

---

## Security

Prepared statements, escaped output, http(s)-only public targets, CSRF, httponly cookies, rate limits, hashed admin password, no credential leakage in HTML errors.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| `SQLSTATE[HY093]` | Duplicate PDO placeholder; pull the latest `LinkService.php` |
| Redirect loop to `/install/` | Missing config or lock file, or not writable |
| Install fails | DB login or directory permissions |
| HTTP 500 | PHP log; keep `display_errors` off in production |
| 400 / 414 | Nginx header buffers |
| Redirect 404 | Path not pure `e`, or unused / disabled length |
| Login rejected | Wrong password or login rate limit |
| 502 | `fastcgi_pass` mismatch |
| Rewrite ignored | Document root is not `public` |
| `ERR_SSL_PROTOCOL_ERROR` | Site TLS not configured |

---

## License

MIT. See [LICENSE](LICENSE).
