# Alternate language implementations

PHP in the repository root is the complete product.
This folder is optional API + redirect cores that share `/sql/schema.sql`.

Parity now:
- nearby random `e_length` (not only +1) in Go, Python, Node, Rust, Java, C#
- unique-key retry on duplicate
- 302 + click log
- token via Bearer / X-API-Token / form `token`
- public http(s) only
- X-Forwarded-For when behind a proxy

Still missing versus PHP: home UI, admin, installer, CSRF, rate limits.
Do not proxy the live site here unless you intend to drop the PHP document root.
