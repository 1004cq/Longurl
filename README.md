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

Copy the environment example:

```bash
cp .env.example .env
```

Set your real values on the server only:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=admin
DB_USER=admin
DB_PASS=CHANGE_ME
BASE_URL=https://your-domain.example
API_TOKEN=CHANGE_ME
MIN_LENGTH=8
MAX_LENGTH=5000
CREATE_PER_MINUTE=20
API_PER_MINUTE=60
PORT=8080
```

Import:

```bash
mysql -u admin -p admin < sql/schema.sql
```

Build and run:

```bash
go mod tidy
go build -o longurl .
set -a
. ./.env
set +a
./longurl
```

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
