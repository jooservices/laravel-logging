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
- `between()`, `since()`, `until()`
- `latest()`, `oldest()`, `limit()`

Identity filters reuse logging semantics. Models use class name and string key.
Strings are named identifiers and are not parsed. Explicit IDs are cast to
strings.
