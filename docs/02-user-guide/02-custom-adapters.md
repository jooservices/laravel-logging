# Custom Adapters

Register custom adapters through the registry:

```php
ActivityLog::register('crawler', CrawlerLogAdapter::class);
```

Custom adapters should implement `LogAdapterInterface`. Most adapters should
extend `BaseLogAdapter` so identity handling, sanitization, queue dispatch, and
synchronous persistence stay consistent with built-in adapters.

Use `replace()` only for intentional overrides:

```php
ActivityLog::replace('activity', CustomActivityLogAdapter::class);
```

Adapter instances are stateful. The registry resolves a fresh instance for each
log, so custom adapters should keep per-record fluent state on the adapter.
