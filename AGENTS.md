# jooservices/laravel-logging

This file adds project-only rules. Workspace root `AGENTS.md` remains canonical
for identity, GitHub account, branch model, commit/PR language, runtime policy,
and the general quality gate.

- PHP `^8.5`, Laravel package: `laravel/framework` `^12|^13`, MongoDB via `mongodb/laravel-mongodb` `^5.7`
- Runtime deps: `jooservices/dto` `^3`, `jooservices/laravel-repository` `^4`
- Namespace **must** be `JOOservices\LaravelLogging\` (uppercase `OO`)
- Store structured activity/audit/security/domain/system records only. No dashboards, analytics product, or observability platform replacement
- Use `jooservices/dto` as the standard for tooling, docs, and AI guidance; use `jooservices/laravel-repository` as the source of truth for repository construction and persistence
- Read the relevant `.github/skills/*/SKILL.md` before non-trivial changes
- Do not assume missing behavior. Stop and report evidence when package APIs, MongoDB Laravel behavior, or JOOservices standards conflict

## Package rules

- The only v1 storage backend is MongoDB collection `activity_logs`.
- Persistence must flow through `ActivityLogData` -> `MongoLogStore` -> `ActivityLogRepository` -> `ActivityLogRecord`.
- `ActivityLogRepository` must receive its model through dependency injection and follow `jooservices/laravel-repository` constructor and trait patterns.
- `ActivityLogRepository` is internal. Do not add a public repository override, repository interface, or repository extension point without an explicit approved need.
- Adapters must not call models directly or bypass the store/repository path.
- Internal payloads should be DTO-first, especially `ActivityLogData`.
- Adapter resolution is registry-based. Do not add hard-coded built-in adapter methods to the manager interface.
- Built-in adapters are configured through `config/laravel-logging.php`; custom adapters use `register()` or `replace()`.
- Adapter instances are stateful and must be resolved fresh for each log.
- String actor, subject, and causer inputs are named identifiers only. Do not parse delimiters or infer IDs.
- Use `byExternal()`, `onExternal()`, and `causedByExternal()` for explicit external type/id references.
- `save()` is synchronous and returns `ActivityLogRecord`.
- Async logging must use `queue(...)->dispatch()`, which dispatches `StoreActivityLogJob`.
- `sync()->dispatch()` records immediately without pushing a queue job.
- Sanitization must recursively redact exact sensitive keys in `properties`, `context`, and `changes`.
- Sanitization runs before payload limiting; payload limits must truncate oversized strings, arrays, depth, and approximate document size before persistence.
- Request context must not log full request payloads, cookies, or auth headers by default.
- Query APIs must reuse logging identity semantics and avoid exposing raw MongoDB details unless required.
- Retention is command-based in v1 and prunes by `occurred_at`.
- Export is command-based in v1, streams JSONL/CSV, and must not overwrite files unless forced.
- Model audit logging is opt-in only through `LogsActivity`; never add global automatic observers.
- Domain event mapping must use the mapper registry and preserve the fallback event-class projection.

## Testing rules

- Do not implement `ActivityLog::fake()`.
- Do not add fake/assertion helpers such as `assertRecorded()` or `assertNothingRecorded()` unless the testing policy is explicitly changed.
- Tests must use full-flow package behavior. Do not mock or fake internal store, repository, model, adapter, or DTO services.
- Allowed fakes are limited to Laravel framework boundaries such as `Queue::fake()`, `Event::fake()`, and temporary filesystem fakes.
- Persistence assertions must hit the real MongoDB test collection, including a real queued job handle when queue dispatch behavior is covered.

## Quality gate

- Pint uses the `per` preset (PER-CS 3.0) as the master formatter. Tune PHPCS/php-cs-fixer around Pint, not the reverse.
- PHPStan runs at level `max` with Larastan and strict-rules (`phpstan.neon.dist` + baseline for legacy findings).
- `composer format:sanity` checks for suspiciously collapsed source, config, docs, workflow, and AI guidance files.
- `composer check` is the local normal gate (lint plus unit and integration suites); `composer ci` is the coverage gate.
- CI required checks and the 90% coverage floor are documented in [`WORKFLOWS.md`](WORKFLOWS.md).
- CaptainHook is required (`composer run post-install-cmd`); never `--no-verify`.
- When code, config, tooling, workflow, or architecture changes, update `docs/`, `AGENTS.md`, and `.github/skills` in the same change.
