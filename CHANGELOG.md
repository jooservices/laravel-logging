# Changelog

All notable changes to `jooservices/laravel-logging` will be documented in this file.

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
