# EEEE Long URL — alternate backend implementations

This directory contains independent backend implementations that follow the shared contract in [`CONTRACT.md`](CONTRACT.md). The original PHP application remains the reference implementation and is not replaced automatically.

| Implementation | Status | Default run mode |
|---|---|---|
| Python | Reference implementation | Python standard library + SQLite |
| Node.js | Planned adapter | See directory README |
| Go | Planned adapter | See directory README |
| Rust | Planned adapter | See directory README |
| Java | Planned adapter | See directory README |
| C# | Planned adapter | See directory README |

Each implementation must be evaluated independently before production use. Do not share a database between implementations without first testing migrations, click counters, expiration semantics, and concurrent allocation.
