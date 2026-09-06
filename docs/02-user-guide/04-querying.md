# Querying

Use `query()` or `records()` to fetch stored log records:

```php
$logs = ActivityLog::query()
    ->type('audit')
    ->action('config.updated')
    ->forSubject($config)
    ->byActor($user)
    ->correlationId($correlationId)
    ->between($from, $to)
    ->latest()
    ->paginate();
```

Available filters:

- `type()`, `adapter()`, `level()`, `action()`
- `forSubject()`, `byActor()`, `causedBy()`
- `correlationId()`, `requestId()`, `traceId()`
- `tenantId()` — filter by top-level `tenant_id` (SaaS multi-tenant apps)
- `actionPrefix()` — `action` starts with the given prefix (e.g. `crawl.`, `http.`)
- `between()`, `since()`, `until()`
- `latest()`, `oldest()`, `limit()`

Identity filters reuse logging semantics. Models use class name and string key.
Strings are named identifiers and are not parsed. Explicit IDs are cast to
strings.

## Terminal record helpers

`latest()` and `oldest()` are sort modifiers. Use terminal helpers when you need
a single record or the record before the latest match:

```php
$latest = ActivityLog::query()
    ->forSubject($target)
    ->action('import.completed')
    ->latestRecord();

$previous = ActivityLog::query()
    ->forSubject($target)
    ->action('import.completed')
    ->previousRecord();
```

`previousRecord()` returns the next-oldest record for the current query
constraints — useful for gap calculations, rate limits, and diffing against the
prior run.

## Aggregations

Count matching records by action or level within the current query window:

```php
$byAction = ActivityLog::query()
    ->adapter('system')
    ->since($from)
    ->countByAction();

$byLevel = ActivityLog::query()
    ->type('system')
    ->actionPrefix('http.')
    ->countByLevel();
```

Returns `array<string, int>` keyed by action or level. Empty values are grouped
under `__empty__`.
