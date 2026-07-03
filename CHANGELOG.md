# Changelog

All notable changes to `jooservices/laravel-logging` will be documented in this file.

## [v1.2.0] - 2026-07-03

### Added

- **Promoted fields** — config `laravel-logging.promoted_fields` copies nested `properties`/`context` paths to top-level document fields before persist; indexes created via `activity-log:indexes`
- **Granular retention rules** — `laravel-logging.retention.rules` match by `adapter`, `level`, and/or `action_prefix` plus `retention_days`
- **Query helpers** — `tenantId()`, `actionPrefix()`, `latestRecord()`, `previousRecord()`, `countByAction()`, `countByLevel()`
- **`tenant_id`** — optional top-level field on adapters via `tenantId()` with MongoDB indexes
- **`ActivityLogStoreFailed` event** — dispatched when `StoreActivityLogJob` fails permanently
- **Coverage gate** — CI enforces ≥90% line coverage via `scripts/check-coverage.php`
- **Docs** — ecosystem decision tree and adapter cookbook

### Changed

- Default `activity-log:prune` (no `--type`/`--days`/`--before`) now runs per-type retention passes from `retention.types`, then configured `retention.rules`
- `InstallActivityLogIndexesCommand` includes `tenant_id` and promoted-field compound indexes

### Migration notes

- Publish or merge config to add `promoted_fields` and `retention.rules` (both default to empty arrays)
- Re-run `php artisan activity-log:indexes` after configuring promoted fields or enabling `tenant_id` queries at scale
- Explicit prune flags (`--type`, `--days`, `--before`) behavior is unchanged

## [v1.1.0] - 2026-06-25

### Added

- Added Laravel 13 support alongside Laravel 12: `laravel/framework` now accepts `^12.0|^13.0`
- Added `orchestra/testbench:^11.0` to `require-dev` and a CI matrix testing both Laravel 12 and Laravel 13 against a real MongoDB service

### Changed

- Bumped the `jooservices/laravel-repository` floor to `^1.3` (the first release with Laravel 13 support) so consumers always pull a repository version verified against Laravel 13
- Updated docs and AI/agent guidance to state the Laravel 12/13 support range

### Notes

- `StoreActivityLogJob` carries a `dto`-package value object, not an Eloquent model, so no queue serialization API is touched directly by this change. Laravel 13 changed the queue job payload serialization format, so drain any pending queues before swapping a Laravel 12 worker for a Laravel 13 worker.

## [v1.0.0] - 2026-05-12

Initial stable release.

### Added

- MongoDB-backed structured logging for Laravel 12 applications on PHP 8.5.
- First-party activity, audit, security, domain, and system log adapters.
- Facade-based logging API with synchronous persistence and queued dispatch support.
- Query helpers for filtering records by log type, actor, subject, correlation, and time range.
- Console tooling for health checks, index installation, pruning, and export workflows.
- Opt-in model audit logging and domain event mapper support.
- Package quality gates covering formatting, static analysis, and automated test execution.
