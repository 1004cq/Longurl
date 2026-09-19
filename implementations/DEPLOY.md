# Alternate implementations deployment

The repository root Go application is the canonical production server.

The runtimes in this directory are optional protocol-compatible alternatives for testing or ecosystem-specific deployments.

## Shared database

All implementations use `/sql/schema.sql`.

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

For the production root Go application, use `/nginx/rewrite.conf`.

## Smoke test

```bash
curl -s http://127.0.0.1:8081/healthz
curl -s -X POST http://127.0.0.1:8081/api/create \
  -H 'Authorization: Bearer CHANGE_ME' \
  -d 'url=https://example.com' \
  -d 'length=100'
```
