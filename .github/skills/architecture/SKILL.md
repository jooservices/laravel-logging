---
name: architecture
description: "Use when changing package architecture, public logging flow, adapters, DTO boundaries, service bindings, or documented design."
---

# Architecture Skill

## Source of truth

- `src/ActivityLogManager.php`
- `src/LogAdapterRegistry.php`
- `src/Adapters/`
- `src/DTO/ActivityLogData.php`
- `src/Stores/MongoLogStore.php`
- `src/Repositories/ActivityLogRepository.php`
- `src/Models/ActivityLogRecord.php`
- `config/laravel-logging.php`
- `docs/00-architecture/01-overview.md`

## Rules

- Preserve the flow: facade -> manager -> registry -> adapter -> `ActivityLogData` -> store -> repository -> model -> MongoDB.
- Keep MongoDB as the only v1 storage backend.
- Do not add SQL storage, migrations, or alternate stores unless the package design is intentionally changed and documented.
- Do not hard-code built-in adapter methods into the manager interface.
- Treat adapters as stateful builders that must be resolved fresh for each log.
- Keep public behavior documented and tested when architecture changes.
- Query, retention, export, model audit, and domain mapper APIs must stay within
  the existing facade, adapter, DTO, store, repository, and model boundaries.

## Stop conditions

- `jooservices/laravel-repository` patterns conflict with the desired repository change.
- Laravel MongoDB model behavior is unclear or differs from current tests.
- A change would break existing public API beyond the task scope.
