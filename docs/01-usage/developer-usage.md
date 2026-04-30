# Developer Usage

Use the facade for normal application code:

```php
ActivityLog::activity()
    ->action('provider.disabled')
    ->by($user)
    ->on($provider)
    ->save();
```

The built-in adapters cover:

- `activity`: normal application and business activities.
- `audit`: data changes with before/after field changes.
- `security`: login, password, 2FA, API key, permission, and suspicious events.
- `domain`: minimal domain event projection through `fromEvent()` / `project()`.
- `system`: command, job, scheduler, and exception flow records.

Custom adapters can be registered without changing the manager interface:

```php
ActivityLog::register('crawler', CrawlerLogAdapter::class);
ActivityLog::crawler()->action('crawler.completed')->save();
```

Sensitive keys are redacted from structured payloads using exact key matching.

Async logging:

```php
ActivityLog::system()
    ->jobCompleted(ProcessProviderJob::class)
    ->queue('logging')
    ->dispatch();
```

`save()` is always synchronous; `queue()` does not change the behavior of `save()`. `sync()->dispatch()` records immediately without pushing a queue job.
