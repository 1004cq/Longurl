# EEEE Node.js backend

This implementation uses Node.js built-ins and a JSON file store so it runs without npm dependencies. It implements `/healthz`, `/api/create`, pure-e redirect paths, click logging, public URL validation, and token authentication.

```bash
cd implementations/node
API_TOKEN=dev-token node server.js
curl http://127.0.0.1:8080/healthz
```

Create a link with the same request contract in [`../CONTRACT.md`](../CONTRACT.md). The JSON store is suitable for development and single-process deployments; use a transactional database adapter before running multiple instances.
