# Deployment guide

Choose exactly one backend implementation per deployment. Do not point two implementations at the same production database until their migrations and concurrency behavior have been verified.

## Python reference

```bash
cd implementations/python
python3 server.py
```

For a service manager, run it as a dedicated unprivileged user and put Nginx in front using `nginx-reverse-proxy.conf`. Set `APP_BASE_URL`, `API_TOKEN`, `DATABASE_URL`, `MIN_LENGTH`, and `MAX_LENGTH` through the environment rather than committing secrets.

## Node.js reference

```bash
cd implementations/node
API_TOKEN='replace-me' node server.js
```

The Node adapter currently uses a single-process JSON store. Use it for local/dev deployments only until a transactional database adapter is added.

## Existing PHP application

The original PHP app remains the production reference and can continue to use the root-level Nginx configuration. The alternate implementations are additive and do not replace it.
