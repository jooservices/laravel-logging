---
name: mongodb-persistence
description: "Use when changing MongoDB model, repository, store, indexes, persistence tests, or Laravel service bindings."
---

# MongoDB Persistence Skill

## Source of truth

- `src/Models/ActivityLogRecord.php`
- `src/Repositories/ActivityLogRepository.php`
- `src/Stores/MongoLogStore.php`
- `src/Jobs/StoreActivityLogJob.php`
- `src/Console/Commands/InstallActivityLogIndexesCommand.php`
- `config/laravel-logging.php`
- `docs/01-usage/mongodb-schema.md`
- `tests/Integration/PersistenceTest.php`

## Rules

- MongoDB collection is `activity_logs` by default.
- Persist only through `MongoLogStore` -> `ActivityLogRepository` -> `ActivityLogRecord`.
- `ActivityLogRepository` must receive `ActivityLogRecord` through dependency injection and follow `jooservices/laravel-repository` constructor and trait patterns.
- Configured model classes must extend `ActivityLogRecord`.
- Do not let adapters or jobs call the model directly.
- Index setup belongs in `activity-log:indexes` and matching docs.
- Nested `properties`, `context`, and `changes` keys are not indexed in v1.

## Stop conditions

- The Laravel MongoDB package API for a model/repository change is not locally verifiable.
- A repository package update would require breaking current persistence tests or public API.
