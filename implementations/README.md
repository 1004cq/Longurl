# Alternate language implementations

The PHP application in the repository root remains the primary/complete EEEE implementation.

This directory contains compatible backend implementations in other languages. They all use the same MySQL tables from `/sql/schema.sql` and the same core rules:

- only pure-`e` paths are valid long links
- the number of `e` characters is the unique ID
- if a requested length is occupied, try the next free length
- redirect with HTTP 302
- only `http` and `https` destination URLs are accepted
- `POST /api/create` requires an API token
- click count and click logs are recorded

Environment variables used by all implementations:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=admin
DB_USER=admin
DB_PASS=YOUR_DB_PASSWORD
BASE_URL=https://eeeeeeeeeeeeee.ee
API_TOKEN=CHANGE_ME
MIN_LENGTH=8
MAX_LENGTH=5000
PORT=8080
```

The alternate versions intentionally focus on the core generator/API/redirect path so you can compare languages without duplicating the entire PHP admin UI. The existing PHP admin can still manage the same MySQL database.
