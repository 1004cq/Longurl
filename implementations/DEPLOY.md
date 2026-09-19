# Alternate implementations deployment

Keep the root PHP app for BaoTa if you want the full UI and admin.
Use another runtime only when you want the redirect/API core in that language.

## Shared database

All implementations use `/sql/schema.sql`.
PHP admin can manage the same rows the other runtimes create.

## Environment

```bash
cp implementations/.env.example implementations/.env
```

Edit secrets locally. Never commit `.env` or a real hostname.

## Docker

```bash
cd implementations
docker compose -f docker-compose.example.yml up -d --build go
```

| Runtime | Host port |
|---|---:|
| Go | 8081 |
| Python | 8082 |
| Node | 8083 |
| Rust | 8084 |
| Java | 8085 |
| C# | 8086 |

## Nginx

Proxy to one backend only. Forward the real client IP:

```nginx
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
large_client_header_buffers 8 32k;
client_header_buffer_size 16k;
```

## Smoke test

```bash
curl -s http://127.0.0.1:8081/healthz
curl -s -X POST http://127.0.0.1:8081/api/create \
  -H 'Authorization: Bearer CHANGE_ME' \
  -d 'url=https://example.com' \
  -d 'length=100'
```

`length` in the JSON is the stored e-count and may differ slightly from 100 when that slot is taken.
