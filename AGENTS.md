# Agent Notes

- This package stores structured logs in MongoDB through `ActivityLogRecord`, `ActivityLogRepository`, and `MongoLogStore`.
- Persistence must go through the repository/store path. Adapters must not call models directly.
- Internal log payloads should be represented by DTOs, especially `ActivityLogData`.
- Adapter resolution is registry-based. Do not add hard-coded built-in adapter methods to the manager interface.
- Adapter instances are stateful and must be resolved fresh for each log.
- String actor, subject, and causer inputs are stored as named identifiers only; do not parse delimiters or infer IDs.
- Use `byExternal()`, `onExternal()`, and `causedByExternal()` for explicit external type/ID references.
- Run `composer validate`, Pint, static analysis, lints, and tests before committing.
- Stop and ask when package APIs, Laravel/MongoDB behavior, or JOOservices conventions differ from the current source of truth.
