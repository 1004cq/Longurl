# EEEE Long URL implementation contract

Every implementation in this directory should preserve the same core redirect behavior as the canonical root Go server.

## Endpoints

| Method | Path | Behavior |
|---|---|---|
| GET | `/` | Home page or service metadata |
| GET | `/healthz` | health response |
| POST | `/api/create` | create a long URL from `url` and `length` |
| GET | `/` + only `e` characters | 302 redirect and click record |
| Any | unknown path | 404 |

## Storage

The reference schema is `../../sql/schema.sql`. The `links` record is keyed by unique `e_length`; click records include time, IP, user agent, referrer and request URI when available.

## Security

Do not render secrets, do not return database exceptions to clients, use parameterized queries, rate-limit creation and authentication, and validate target URLs.
