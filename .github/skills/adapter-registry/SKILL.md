---
name: adapter-registry
description: "Use when changing adapter registration, manager resolution, magic methods, custom adapters, or adapter tests."
---

# Adapter Registry Skill

## Source of truth

- `src/Contracts/ActivityLogManagerInterface.php`
- `src/Contracts/LogAdapterRegistryInterface.php`
- `src/Contracts/LogAdapterInterface.php`
- `src/ActivityLogManager.php`
- `src/LogAdapterRegistry.php`
- `config/laravel-logging.php`
- `tests/Unit/RegistryManagerTest.php`

## Rules

- Manager contract exposes `adapter(string|BackedEnum $name)`, `register()`, `replace()`, and `__call()`.
- Do not add hard-coded `activity()`, `audit()`, `security()`, `domain()`, or `system()` methods to the manager interface.
- Built-in adapters come from config/service provider registration.
- `register()` throws when an adapter already exists.
- `replace()` intentionally overrides.
- `resolve()` returns a fresh adapter instance every call.
- Missing adapters throw a clear exception.
- Resolved adapters must implement `LogAdapterInterface`.
- Magic adapter methods reject parameters.

## Queue contract

- `LogAdapterInterface` must include `dispatch(): void`.
- `queue(?string $queue = null)->dispatch()` dispatches `StoreActivityLogJob`.
- `sync()->dispatch()` records immediately.
- Adapter tests must use real package services; do not add fake store, repository, or adapter layers.

## Domain mapper contract

- `domain()->fromEvent($event)` checks registered mappers before fallback projection.
- If no mapper supports the event, keep the event-class fallback behavior.
- Mappers must not bypass adapter state or the normal store/repository path.
- `save()` remains synchronous and returns `ActivityLogRecord`.
