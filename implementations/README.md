# Alternate language implementations

The repository root Go application is the canonical EEEE production server.

This directory contains optional protocol-compatible implementations in:
- Go (legacy standalone sample)
- Python
- Node/TypeScript
- Rust
- Java
- C#

They share `/sql/schema.sql` and the same core e-length redirect contract.

Do not deploy several runtimes on the same hostname unless you intentionally split routes.
