# EEEE Long URL

A deliberately long-URL generator. The generated path contains only the letter `e`, and the number of `e` characters is the unique link ID.

This is not a shortener.

- Input: `https://example.com/test`
- Output: `https://your-domain.example/eeeeeeeeee` (10 e's)

> Security note: never commit `config/local.php`. The installer creates it outside the web root and stores database credentials, the admin password hash, and the API token.

## Features

- Pure-`e` long URLs
- Automatic collision handling when a requested length is already taken
- 302 redirects
- Click counting and visit logs
- Admin dashboard and link management
- API for creating links
- CSRF protection and session-based admin authentication
- Per-IP rate limiting for creation, API, and login
- Web installer
- No Composer, Node.js, or framework dependency

## Layout

```
config/          # local.php generated on install (outside web root)
public/          # web root
  index.php
  admin/
  install/
  assets/
src/
sql/schema.sql
nginx/rewrite.conf
storage/
```

Suggested server layout:

```
/www/wwwroot/YOUR_DOMAIN/e/
  public/   ← Nginx root
  config/
  src/
  ...
```

## Requirements

- Ubuntu + Nginx
- PHP 8.3 / 8.4 / 8.5
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`, `session`, `filter`
- MySQL 5.7+ / 8.x
- No Node.js
- No Composer

## MySQL

```sql
CREATE DATABASE eeee_longurl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eeee_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON eeee_longurl.* TO 'eeee_user'@'localhost';
FLUSH PRIVILEGES;
```

## Permissions

```bash
chown -R www:www /www/wwwroot/YOUR_DOMAIN/e
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/config
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/storage
```

After installation, the installer attempts to set:

```
config/local.php          0640
storage/installed.lock    0640
```

## Nginx / 宝塔

Set the website root to:

```
/www/wwwroot/YOUR_DOMAIN/e/public
```

For 宝塔/BT Panel, put only the rewrite rule from `nginx/rewrite.conf` into **网站 → 设置 → 伪静态**:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Do not paste a second PHP/FastCGI `location ~ \.php$` block if the panel already manages PHP for the site.

For very long paths (for example 1000–5000 e's), you may also need larger URI/header buffers in the site's `server {}` configuration:

```nginx
large_client_header_buffers 8 32k;
client_header_buffer_size 16k;
```

Then reload Nginx.

## Install

1. Open `https://YOUR_DOMAIN/install/`
2. Enter database settings, the root website URL, and an admin password
3. The installer validates the URL, imports `sql/schema.sql`, creates `config/local.php`, and writes `storage/installed.lock`
4. Open `/admin/`

The website URL must be the site root, for example:

```
https://eeeeeeeeeeeeee.ee
```

Do not enter a URL with a sub-path such as `https://example.com/test`.

## API

`POST /api/create`

Use one of these authentication headers:

```http
Authorization: Bearer YOUR_TOKEN
```

or:

```http
X-API-Token: YOUR_TOKEN
```

The token is intentionally **not accepted in the query string**, to reduce accidental leakage through browser history, referrers, analytics, and access logs.

Body fields:

- `url`
- `length`

Example response:

```json
{
  "success": true,
  "url": "https://your-domain.example/eeeeeeeeee",
  "length": 10,
  "target": "https://example.com"
}
```

## Long-link allocation

The link ID is the path length.

If 100 e's is already used and a new request asks for length 100, the service advances to the next free length (101, 102, ...).

A unique database index on `links.e_length` is the final concurrency guard. If two requests race for the same free length, the losing request retries with the next candidate instead of failing.

## Troubleshooting

- **500 after install:** check `config/local.php` permissions and the PHP error log.
- **Installer cannot write config:** `config/` and `storage/` must be writable by the PHP user.
- **Long path returns 414 / 400:** increase Nginx header/URI buffers.
- **Redirect loop to `/install/`:** `storage/installed.lock` or `config/local.php` is missing.
- **Admin login fails:** default rate limit is 8 attempts per minute per IP.
- **PDO error / Service unavailable:** verify database credentials and that `pdo_mysql` is installed.
- **Pure-e link returns 404:** confirm the Nginx rewrite rule and make sure the website root points to `public/`.
- **Mixed-character paths 404:** expected behavior; only `/e+` resolves.

## Security

- PDO prepared statements
- `htmlspecialchars` on HTML output
- Destination URL allow-list: only `http` / `https`
- Local/private literal IPs rejected as destinations
- CSRF protection on frontend, admin, and installer forms
- `password_hash` / `password_verify`
- Session cookie: HttpOnly, SameSite=Lax, Secure on HTTPS
- API token via headers only
- Rate limits on create, API, and admin login
- Config stored outside the public directory
- Installer lock file after successful setup
- Database credentials are never printed in production errors

## Deployment update

If updating an existing installation, keep these files before replacing code:

```
config/local.php
storage/installed.lock
```

Do not overwrite them with example files.
