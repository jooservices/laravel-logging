# MongoDB Persistence

MongoDB is the only v1 storage backend. The default collection is
`activity_logs`.

Persistence must flow through:

```text
ActivityLogData -> MongoLogStore -> ActivityLogRepository -> ActivityLogRecord
```

Adapters and jobs must not write to the model directly. `StoreActivityLogJob`
receives `ActivityLogData` and records it through `LogStoreInterface`.

`ActivityLogRepository` follows `jooservices/laravel-repository`: it receives
`ActivityLogRecord` through dependency injection, extends `EloquentRepository`,
and uses the repository CRUD trait for writes.

The configured model class must extend `ActivityLogRecord`. The repository
binding stays internal to the package and is not a public configuration or
extension point in v1.

Persistence and command tests must use the real flow through facade or manager,
adapter, DTO, store, repository, model, and MongoDB collection. Do not add
fake store, repository, model, adapter, or DTO layers to this repository.

Run the index command after installation:

```bash
php artisan activity-log:indexes
php artisan activity-log:doctor
php artisan activity-log:doctor --check-indexes
```

The command creates top-level indexes for classification, actor, subject,
causer, trace, and time fields. Nested `properties`, `context`, and `changes`
keys are not indexed in v1.
