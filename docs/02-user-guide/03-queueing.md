# Queueing

`save()` always writes synchronously and returns `ActivityLogRecord`.

```php
$record = ActivityLog::activity()
    ->action('crawler.completed')
    ->bySystem()
    ->save();
```

Async logging uses `queue(...)->dispatch()`:

```php
ActivityLog::activity()
    ->action('crawler.completed')
    ->bySystem()
    ->queue('logging')
    ->dispatch();
```

The queued job receives `ActivityLogData` and records through
`LogStoreInterface`. Passing a queue name applies it to the dispatched job.

Use `sync()->dispatch()` when code wants the dispatch-style API but immediate
persistence:

```php
ActivityLog::system()
    ->commandStarted('crawler:run')
    ->sync()
    ->dispatch();
```
