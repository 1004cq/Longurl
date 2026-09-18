# EEEE Long URL

A long-URL generator. The path contains only the letter `e`. Length of that path is the unique ID.

This is not a shortener.

Example:

- input: `https://example.com/test`
- output: `https://your-domain.example/eeeeeeeeee` (10 e's)

Do not commit `config/local.php`. That file is created by the installer and holds database credentials, the admin password hash, and the API token.

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

- Ubuntu + Nginx + PHP 8.3/8.4/8.5
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`, `session`, `filter`
- MySQL 5.7+ / 8.x
- No Node.js, no Composer

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

## Nginx / 宝塔

1. Website root: `/www/wwwroot/YOUR_DOMAIN/e/public`
2. PHP version: 8.x
3. Paste `nginx/rewrite.conf` into 网站 → 设置 → 配置文件 / 伪静态
4. Increase header buffers if you allow 1000+ e characters
5. Reload Nginx

## Install

1. Open `https://YOUR_DOMAIN/install/`
2. Fill database + public site URL + admin password
3. Installer writes `config/local.php` and `storage/installed.lock`
4. Open `/admin/`

After install, copy the API token from `config/local.php` on the server. Do not put it in git.

## API

`POST /api/create`

Headers: `Authorization: Bearer TOKEN`

Body: `url`, `length`

```json
{
  "success": true,
  "url": "https://your-domain.example/eeeeeeeeee",
  "length": 10,
  "target": "https://example.com"
}
```

## Troubleshooting

- 500 after install: check `config/local.php` permissions and PHP error log. Display errors stay off in production.
- Installer cannot write config: `config/` and `storage/` must be writable by the PHP user.
- Long path 414 / 400: raise `large_client_header_buffers` and `client_header_buffer_size`.
- Redirect loop to `/install/`: missing `storage/installed.lock` or `config/local.php`.
- Admin login fails: rate limit is 8 attempts / minute / IP.
- PDO exception on page: credentials wrong; the app never prints the password.
- Path with mixed characters 404s by design. Only `/e+` resolves.

## Security

- PDO prepared statements
- `htmlspecialchars` on output
- URL allow-list: `http`/`https` only
- CSRF on forms
- `password_hash` / `password_verify`
- Session cookie: httponly, samesite Lax, secure when HTTPS
- Rate limits on create, API, login
- Config lives outside the public directory
