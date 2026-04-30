# Architecture Overview

The package has four layers:

- Facade and manager: `ActivityLog` resolves adapters by name through the registry.
- Adapters: fluent, stateful builders that produce `ActivityLogData`.
- DTOs and services: DTOs carry structured data; sanitizer and request context resolver keep common behavior focused.
- Persistence: `MongoLogStore` records through `ActivityLogRepository`, which extends `jooservices/laravel-repository`.

Adapters are intentionally fresh per resolve call because fluent state must not leak between logs. Built-in adapter names are registered from config, not hard-coded as manager methods.

String actor, subject, and causer inputs are named identifiers only. Use `byExternal()`, `onExternal()`, and `causedByExternal()` when a type and ID are needed.

`save()` is synchronous. Async logging is explicit through `queue(...)->dispatch()`, which dispatches `StoreActivityLogJob`. `sync()->dispatch()` records immediately without pushing a queue job.
