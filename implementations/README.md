# Alternate language implementations

The PHP app in the repository root is the complete product (UI, admin, installer).

This folder holds optional compatible backends for API + pure-`e` redirects. They share `/sql/schema.sql`.

Rules:
- path may contain only the letter `e`
- unique ID is `e_length`
- requested length is preferred; if taken, pick a free nearby length at random
- unique-index collisions retry
- HTTP 302 + click log
- only public http/https targets
- `POST /api/create` needs a token (`Authorization: Bearer`, `X-API-Token`, or form `token`)

Placeholders only — never commit a real domain or password:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=eeee_longurl
DB_USER=eeee_user
DB_PASS=CHANGE_ME
BASE_URL=https://your-domain.example
API_TOKEN=CHANGE_ME
MIN_LENGTH=8
MAX_LENGTH=5000
PORT=8080
```

Do not point the live PHP site at these services unless you intend to replace the document root.
