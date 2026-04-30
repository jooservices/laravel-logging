# Risks, Gaps, And Changelog

## Current Limitations

- MongoDB is the only v1 storage backend.
- Queue logging is available only through `queue(...)->dispatch()`.
- `save()` remains synchronous by design.
- Retention is command-based and prunes by `occurred_at`.
- Indexes are created by `php artisan activity-log:indexes`; normal requests do
  not create indexes.
- Nested `properties`, `context`, and `changes` keys are not indexed in v1.
- Model audit logging is opt-in only through `LogsActivity`.
- The package does not provide SQL storage, a UI dashboard, global automatic
  model observers, or observability-stack integrations.

## Changelog

### Unreleased

- Documented synchronous `save()` versus queued `dispatch()` behavior.
- Documented MongoDB index command and integration-test MongoDB requirement.
- Added package-specific GitHub Copilot instructions and AI skills for
  architecture, workflow, testing, docs sync, adapter registry, and MongoDB
  persistence.
- Added a format sanity check for suspiciously collapsed source, config, docs,
  workflow, and AI guidance files.
- Added query, prune, export, batch/workflow, opt-in model audit, and domain
  mapper APIs.
