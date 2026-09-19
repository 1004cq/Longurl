# Alternate implementations deployment

The root PHP app remains the easiest choice for BaoTa/BT Panel because it already includes the complete UI, admin, installer and analytics.

Use an alternate implementation when you specifically want the redirect/API core in another runtime.

## Shared database

All implementations use the same tables from:

```
/sql/schema.sql
```

So you can keep the PHP admin UI and let another runtime handle pure-`e` redirects/API, as long as both use the same MySQL database.

## Environment

Copy:

```bash
cp implementations/.env.example implementations/.env
```

Then edit the real password/token locally. Do not commit the real `.env`.

## Docker Compose

From the repository root:

```bash
cd implementations
docker compose -f docker-compose.example.yml up -d --build
```

Ports:

| Runtime | Host port |
|---|---:|
| Go | 8081 |
| Python | 8082 |
| Node/TypeScript | 8083 |
| Rust | 8084 |
| Java | 8085 |
| C# | 8086 |

Run only the service you want in production.

Example:

```bash
docker compose -f docker-compose.example.yml up -d --build go
```

## BaoTa / Nginx reverse proxy

If Go is listening on `127.0.0.1:8081`, use:

```nginx
location / {
    proxy_pass http://127.0.0.1:8081;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Do not proxy the same domain to several implementations at once unless you deliberately split routes.

## API smoke test

```bash
curl -X POST http://127.0.0.1:8081/api/create \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -d 'url=https://example.com' \
  -d 'length=50'
```

Expected JSON contains:

```json
{
  "success": true,
  "length": 50,
  "target": "https://example.com"
}
```

Then test the generated pure-`e` path with `curl -I`; it should return HTTP 302.

## Production recommendation

For the current EEEE site:

- PHP: full application/UI/admin/installer
- Go or Rust: strongest candidates if you later want a small dedicated redirect service
- Python/Node: convenient for fast iteration
- Java/C#: suitable when running in those ecosystems already

Do not replace the working PHP deployment just because the alternate implementations exist. They are optional compatible backends.
