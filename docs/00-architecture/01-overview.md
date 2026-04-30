# Architecture Overview

`jooservices/laravel-logging` stores structured application records in MongoDB.
The runtime flow is intentionally narrow:

```text
Developer API
-> ActivityLog facade
-> ActivityLogManager
-> LogAdapterRegistry
-> adapter
-> ActivityLogData
-> MongoLogStore
-> ActivityLogRepository
-> ActivityLogRecord
-> MongoDB activity_logs
```

The package is not a Laravel Log, Monolog, Sentry, OpenTelemetry, Loki, ELK, or
event-sourcing replacement. It focuses on durable activity, audit, security,
domain, and system records.

Adapters are stateful fluent builders. The registry resolves a fresh instance
for every log so state cannot leak across records.

The manager interface stays registry-based. Built-in adapters are configured in
`config/laravel-logging.php`, and custom adapters use `register()` or
`replace()` instead of new manager methods.

`ActivityLogRepository` is an internal repository aligned to
`jooservices/laravel-repository`. It is not a public repository customization
point in v1.

`save()` is synchronous and returns `ActivityLogRecord`. Async logging uses
`queue(...)->dispatch()`, which dispatches `StoreActivityLogJob`.
`sync()->dispatch()` records immediately.
