# Alternate language implementations

PHP in the repo root is the complete product.

Recommended production split:
- PHP: `/`, `/admin`, `/install`, `POST /?action=create`, `POST /api/create`
- Go on `127.0.0.1:8081`: `GET /e+` only

See `go/README.md` and `../nginx/rewrite.conf`.

Other languages in this folder are optional protocol samples. Do not proxy the whole hostname to them.
