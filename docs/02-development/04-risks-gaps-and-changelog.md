# Risks, Gaps, And Changelog

## Current Limitations

- MongoDB is the only v1 storage backend.
- Queue logging is available only through `queue(...)->dispatch()`.
- `save()` remains synchronous by design.
- Retention is command-based and prunes by `occurred_at`; prune defaults to
  dry-run unless `--force` is used.
- Export is command-based, streams JSONL/CSV, and refuses to overwrite files
  unless `--force` is used.
- Sanitization runs before payload limiting for `properties`, `context`, and
  `changes`.
- `ActivityLogRepository` is internal and not a supported public extension point in v1.
- Laravel 13 support is blocked until `jooservices/laravel-repository` publishes
  compatible Illuminate constraints; version 1.1.0 requires Illuminate 12.
- CI now runs the coverage path through `composer run ci`, but a hard coverage
  threshold is deferred until coverage reaches at least 90%. Current measured
  statement coverage is 82.09%.
- Indexes are created by `php artisan activity-log:indexes`; normal requests do
  not create indexes.
- `php artisan activity-log:doctor` reports runtime readiness and can verify
  expected indexes with `--check-indexes`, but it does not repair configuration
  or connectivity problems automatically.
- Nested `properties`, `context`, and `changes` keys are not indexed in v1.
- Model audit logging is opt-in only through `LogsActivity`.
- The package does not provide SQL storage, a UI dashboard, global automatic
  model observers, or observability-stack integrations.

## Changelog

### Unreleased

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
