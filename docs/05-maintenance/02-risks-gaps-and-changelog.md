# Risks, Gaps, And Changelog

## Current Limitations

- MongoDB is the only v1 storage backend.
- Queue logging is available only through `queue(...)->dispatch()`.
- `save()` remains synchronous by design.
- Retention is command-based and prunes by `occurred_at`; prune defaults to
  dry-run unless `--force` is used.
- Optional collection-wide TTL (`laravel-logging.ttl`) is a coarse max lifetime
  only — prefer type-aware prune for audit vs system retention.
- Export is command-based, streams JSONL/CSV, and refuses to overwrite files
  unless `--force` is used.
- Sanitization and payload limiting run in the store for every write path
  (including `recordMany`), and again in adapters for nested bags.
- `ActivityLogRepository` is internal and not a supported public extension point in v1.
- Requires `jooservices/dto` ^3 and `jooservices/laravel-repository` ^4; Laravel
  `^12|^13` is supported.
- CI enforces ≥90% line coverage via `scripts/check-coverage.php` on the Laravel
  12 clover report (`composer run ci` / Coverage upload).
- Indexes are created by `php artisan activity-log:indexes`; normal requests do
  not create indexes.
- `php artisan activity-log:doctor` reports runtime readiness and can verify
  expected indexes with `--check-indexes`, but it does not repair configuration
  or connectivity problems automatically.
- Nested `properties`, `context`, and `changes` keys are not indexed in v1
  unless promoted via `promoted_fields`.
- Model audit logging is opt-in only through `LogsActivity` (safe defaults:
  dirty-only, skip empty, except sensitive + `$hidden` attributes).
- The package does not provide SQL storage, a UI dashboard, global automatic
  model observers, or observability-stack integrations.

## Changelog

### Unreleased

- Hardened model-audit defaults and store-level sanitization/limiting.
- Mongo `$group` aggregations; prune `deleteMany` by uuid; `{type, occurred_at}` index.
- Fixed TTL vs ascending `occurred_at` index conflict; request-scoped IDs;
  idempotent queue writes on `uuid`.
- Document budget applies to persistence documents; limiter fails closed on
  invalid JSON; `tenantId()` on `LogAdapterInterface`.
- Documented synchronous `save()` versus queued `dispatch()` behavior.
- Documented MongoDB index command and integration-test MongoDB requirement.
- Added `activity-log:doctor` for runtime health checks.
- Removed unsupported public repository customization guidance.
- Enforced a no-internal-mocks, real-MongoDB persistence testing policy.
- Added package-specific GitHub Copilot instructions and AI skills for
  architecture, workflow, testing, docs sync, adapter registry, and MongoDB
  persistence.
- Added a format sanity check for suspiciously collapsed source, config, docs,
  workflow, and AI guidance files.
- Added query, prune, export, batch/workflow, opt-in model audit, and domain
  mapper APIs.
- Added production-operation hardening for doctor, prune, export, recursive
  sanitization, and payload limiting.
