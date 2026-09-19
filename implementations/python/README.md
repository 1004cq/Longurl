# EEEE Python backend

A dependency-free reference backend using `http.server` and SQLite.

```bash
cd implementations/python
export API_TOKEN=dev-token
python3 server.py
```

Health check:

```bash
curl http://127.0.0.1:8080/healthz
```

Create a link:

```bash
curl -X POST http://127.0.0.1:8080/api/create \
  -H 'Authorization: Bearer dev-token' \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://example.com/test","length":20}'
```

This reference implementation covers creation, allocation, redirect logging, SQLite storage, and health/API responses. It intentionally does not reproduce the PHP admin HTML console yet; production deployments should place it behind TLS and a reverse proxy.
