# Agent Notes

- This package stores structured logs in MongoDB through `ActivityLogRecord`, `ActivityLogRepository`, and `MongoLogStore`.
- Runtime target is PHP 8.5 and Laravel 12.
- The only v1 storage backend is MongoDB collection `activity_logs`.
- Persistence must go through the repository/store path. Adapters must not call models directly.
- Internal log payloads should be represented by DTOs, especially `ActivityLogData`.
- Adapter resolution is registry-based. Do not add hard-coded built-in adapter methods to the manager interface.
- Built-in adapters are configured through `config/laravel-logging.php`; custom adapters use `register()` or `replace()`.
- Adapter instances are stateful and must be resolved fresh for each log.
- String actor, subject, and causer inputs are stored as named identifiers only; do not parse delimiters or infer IDs.
- Use `byExternal()`, `onExternal()`, and `causedByExternal()` for explicit external type/ID references.
- `save()` is synchronous and returns `ActivityLogRecord`; async logging must use `queue(...)->dispatch()`. `sync()->dispatch()` records immediately.
- Sanitization must recursively redact exact sensitive keys in `properties`, `context`, and `changes`.
- Request context must not log full request payloads, cookies, or auth headers by default.
- Pint is the master formatter. Tune PHPCS/php-cs-fixer around Pint, not the reverse.
- Run `composer validate`, Pint, static analysis, lints, and tests before committing.
- Stop and ask when package APIs, Laravel/MongoDB behavior, or JOOservices conventions differ from the current source of truth.
