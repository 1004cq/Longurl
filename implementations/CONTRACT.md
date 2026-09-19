# EEEE Long URL implementation contract

Every implementation in this directory must preserve the same public behavior.

## Endpoints

| Method | Path | Behavior |
|---|---|---|
| GET | `/` | Home page or JSON service metadata, depending on implementation mode |
| GET | `/healthz` | `200` JSON health response |
| POST | `/api/create` | Create a long URL from `url` and `length` |
| GET | `/` + only `e` characters | `302` redirect to the stored target and record a click |
| Any | unknown path | `404` |

## Create request

Accept form data or JSON:

```json
{"url":"https://example.com/test","length":100}
```

Authentication accepts `Authorization: Bearer TOKEN`, `X-API-Token: TOKEN`, or a `token` request field. The target must be a valid public `http` or `https` URL. The actual allocated length may be near the requested length when a slot is occupied.

## Storage

The reference schema is `../../sql/schema.sql`. Implementations should use SQLite by default and allow a production database URL through configuration. The `links` record is keyed by unique `e_length`; click records must include time, IP, user agent, referrer, and request URI when available.

## Security

Do not render secrets, do not return database exceptions to clients, use parameterized queries, rate-limit creation and authentication, and never trust forwarded IP headers unless the deployment explicitly configures trusted proxies.
