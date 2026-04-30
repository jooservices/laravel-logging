# Adapter Registry

The registry owns adapter lookup and custom extension points.

- `register()` adds a new adapter and throws if the name already exists.
- `replace()` intentionally overrides an adapter.
- `resolve()` returns a fresh adapter instance every time.
- Missing adapters throw a clear exception.
- Resolved adapters must implement `LogAdapterInterface`.
- Magic adapter methods reject parameters.

Built-in adapters are configured, not hard-coded into the manager interface:

- `activity`
- `audit`
- `security`
- `domain`
- `system`

Custom adapters normally extend `BaseLogAdapter` and implement the relevant
contract.

```php
ActivityLog::register('crawler', CrawlerLogAdapter::class);

ActivityLog::crawler()
    ->action('crawler.completed')
    ->bySystem()
    ->save();
```

`ActivityLog::crawler()` is sugar for `ActivityLog::adapter('crawler')`.
