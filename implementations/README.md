# Alternate language implementations

PHP in the repo root is the **only complete product**: installer, bilingual UI, admin, analytics, CSRF, rate limits.

`implementations/*` are compatible **API + redirect** services. They can share the same MySQL tables.
`implementations/web/index.html` is a slim home page. To use it, serve `/assets` from the PHP `public/assets` directory and accept `POST /?action=create` the same way PHP does.

What is aligned across Go / Python / Node / Rust / Java / C#:
- nearby random `e_length`
- unique-index retry
- 302 + click log
- token API
- public http(s) only

What is **not** cloned six times on purpose:
- BaoTa web installer
- full admin V2
- i18n + polished CSS animations

If you need a second production stack, pick **one** language (Go) and keep PHP as the admin UI on the same database. Do not run two public generators on one hostname.
