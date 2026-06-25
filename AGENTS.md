# JOOservices Laravel Logging Agent Instructions

This repository is a PHP 8.5 / Laravel 12/13 package named `jooservices/laravel-logging`.

## Before coding

- Inspect `git status`, `composer.json`, relevant docs, config, tests, and source files first.
- Read the relevant `.github/skills/*/SKILL.md` files before non-trivial changes.
- Use `jooservices/dto` as the standard for tooling, docs, AI guidance, and PHP style; apply only relevant patterns.
- Use `jooservices/laravel-repository` as the source of truth before changing repository construction or persistence.
- Do not assume missing behavior. Stop and report evidence when package APIs, MongoDB Laravel behavior, or JOOservices standards conflict.

## Git workflow

- Use exactly two long-lived branches: `master` for production releases and `develop` for integration.
- Branch all normal feature, chore, docs, and refactor work from `develop`, and target normal pull requests back to `develop`.
- Do not use `main` as a long-lived branch. If `main` appears, report it instead of deleting it silently.
- Release tags come from `master`; direct work on `master` is limited to approved hotfixes.
- Hotfix branches start from `master`, target `master`, and must be merged back to both `master` and `develop`.

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
- Do not implement `ActivityLog::fake()`.
- Do not add fake/assertion helpers such as `assertRecorded()` or `assertNothingRecorded()` unless the testing policy is explicitly changed.
- Tests in this repository must use full-flow package behavior. Do not mock or fake internal store, repository, model, adapter, or DTO services.
- Allowed fakes are limited to Laravel framework boundaries such as `Queue::fake()`, `Event::fake()`, and temporary filesystem fakes.
- Persistence assertions must hit the real MongoDB test collection, including a real queued job handle when queue dispatch behavior is covered.

## Quality and docs gate

- Pint is the master formatter. Tune PHPCS/php-cs-fixer around Pint, not the reverse.
- `composer run format:sanity` checks for suspiciously collapsed source, config, docs, workflow, and AI guidance files.
- `composer run check` is the local normal gate: lint plus the normal test suite.
- `composer run ci` is the CI/coverage gate: lint plus coverage tests.
- Before commit, run `composer validate --strict`, `composer run lint:fix`, `composer run lint:all`, `composer run test`, and `composer run check`.
- Also run `composer run test:coverage`, `composer audit`, and `composer run ci` when configured and environment support is available.
- Install and verify CaptainHook locally with `composer run post-install-cmd`; do not bypass hooks with `--no-verify`.
- Fix all warnings, notices, and errors. Do not commit when checks fail.
- Run `git diff --check` as part of the local gate.
- If code, config, tooling, workflow, architecture, or user-facing behavior changes, update relevant docs before commit.
- If contributor workflow, package rules, commands, or architecture guidance changes, update `AGENTS.md` and `.github/skills` before commit.
- After successful work, commit local changes with the configured author and leave the working tree clean.

Commit author:

- name: Viet Vu
- email: jooservices@gmail.com
