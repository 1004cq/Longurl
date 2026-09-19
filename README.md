# EEEE Long URL

[English](README.md) · [中文](README.zh-CN.md)

EEEE is a long-URL generator powered by **Go + Vue 3 + Three.js + MySQL**.

PHP has been completely removed from the primary application.

A generated link contains only the letter `e` in its path. The number of `e` characters is the unique identifier.

```
Original:  https://example.com/test
Generated: https://your-domain.example/eeeeeeeeee
```

## Architecture

- Go: HTTP server, API, redirects, rate limiting, MySQL access, static file serving
- Vue 3: browser UI
- Three.js: animated background
- MySQL: links, click logs, rate limits
- Nginx/BaoTa: reverse proxy to the Go process

The Go binary embeds `public/`, so the production server does not need PHP, PHP-FPM, Composer, or Node.js.

## Requirements

- Go 1.23+ for building
- MySQL 5.7+ / 8.x
- Nginx recommended for HTTPS/reverse proxy
- No PHP required

## Setup

Create the MySQL database/user first. Then build and start Go:

```bash
go mod tidy
go build -o longurl .
./longurl
```

On a fresh install the server starts even without database credentials and redirects the site to:

```
/install/
```

The Go installer tests MySQL, imports `sql/schema.sql`, writes `.env` with mode 0640, hashes the admin password, and generates the API/session secrets. No PHP installer is involved.

After installation:

- `/admin/login` — admin sign-in
- `/admin/` — dashboard
- Links — search, pagination, edit target/expiry, enable/disable, delete, CSV export
- Analytics — counters, recent clicks, 7-day activity, click CSV export
- Settings — base URL, e-length range, rate limits, API-token rotation, password change

You can still provision `.env` manually from `.env.example` instead of using the web installer.

Health check:

```bash
curl http://127.0.0.1:8080/healthz
```

## Nginx / BaoTa

Proxy the whole site to Go:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

large_client_header_buffers 8 32k;
client_header_buffer_size 16k;
```

The site root no longer needs a PHP runtime directory.

## Endpoints

- `GET /` — Vue 3 homepage
- `GET /healthz` — health check
- `GET /api/config` — public runtime configuration
- `POST /api/create` — create a long URL
- `GET /eeee...` — 302 redirect by e-count
- `GET/POST /install/` — first-run Go installer
- `GET /admin/` — Go administration console
- unknown paths — 404 UI

Browser creation is rate-limited. API callers may send `Authorization: Bearer TOKEN` or `X-API-Token`.

## Security

- prepared SQL statements
- public/private IP filtering for target URLs
- request rate limiting
- API token support
- no database exceptions returned to clients
- basic security headers
- no secrets committed to the repository

## Alternate implementations

`implementations/` still contains optional Python, Node/TypeScript, Rust, Java, C# and legacy Go protocol-compatible examples.

The repository root Go application is the canonical production implementation.

## License

MIT.
