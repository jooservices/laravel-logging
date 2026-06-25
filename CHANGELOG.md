# Changelog

All notable changes to `jooservices/laravel-logging` will be documented in this file.

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
