# JOOservices Laravel Logging Agent Instructions

This repository is a PHP 8.5 / Laravel 12 package named `jooservices/laravel-logging`.

## Before coding

- Inspect `git status`, `composer.json`, relevant docs, config, tests, and source files first.
- Read the relevant `.github/skills/*/SKILL.md` files before non-trivial changes.
- Use `jooservices/dto` as the standard for tooling, docs, AI guidance, and PHP style; apply only relevant patterns.
- Use `jooservices/laravel-repository` as the source of truth before changing repository construction or persistence.
- Do not assume missing behavior. Stop and report evidence when package APIs, MongoDB Laravel behavior, or JOOservices standards conflict.

## Package rules

- The only v1 storage backend is MongoDB collection `activity_logs`.
- Persistence must flow through `ActivityLogData` -> `MongoLogStore` -> `ActivityLogRepository` -> `ActivityLogRecord`.
- `ActivityLogRepository` must receive its model through dependency injection and follow `jooservices/laravel-repository` constructor and trait patterns.
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
- Request context must not log full request payloads, cookies, or auth headers by default.
- Query APIs must reuse logging identity semantics and avoid exposing raw MongoDB details unless required.
- Retention is command-based in v1 and prunes by `occurred_at`.
- Model audit logging is opt-in only through `LogsActivity`; never add global automatic observers.
- Domain event mapping must use the mapper registry and preserve the fallback event-class projection.

## Quality and docs gate

- Pint is the master formatter. Tune PHPCS/php-cs-fixer around Pint, not the reverse.
- `composer run format:sanity` checks for suspiciously collapsed source, config, docs, workflow, and AI guidance files.
- Before commit, run `composer validate`, `composer run lint:fix`, `composer run lint:all`, `composer run test`, and `composer run check`.
- Also run `composer audit` and `composer run ci` when configured and environment support is available.
- Install and verify CaptainHook locally with `composer run post-install-cmd`; do not bypass hooks with `--no-verify`.
- Fix all warnings, notices, and errors. Do not commit when checks fail.
- If code, config, tooling, workflow, architecture, or user-facing behavior changes, update relevant docs before commit.
- If contributor workflow, package rules, commands, or architecture guidance changes, update `AGENTS.md` and `.github/skills` before commit.
- After successful work, commit local changes with author `Viet Vu <jooservices@gmail.com>` and leave the working tree clean.
