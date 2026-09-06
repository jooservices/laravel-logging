# Adapter Cookbook

Patterns for custom adapters. The package does **not** ship domain-specific
adapters (e.g. crawler, e-commerce checkout). Apps register their own.

## Ops adapter (jobs, pipelines, integrations)

For high-volume technical logs that operators query by subject, action prefix,
and promoted dimensions.

```php
final class OpsLogAdapter extends BaseLogAdapter
{
    protected string $type = 'ops';
    protected string $adapter = 'ops';
    protected ?string $level = 'info';

    public function crawlStarted(int $siteId, string $runId): static
    {
        return $this
            ->action('crawl.started')
            ->level('info')
            ->bySystem()
            ->onExternal('site', $siteId)
            ->correlationId($runId)
            ->properties([
                'site_id' => $siteId,
                'crawl_run_id' => $runId,
            ]);
    }
}
```

Register and use:

```php
// AppServiceProvider or logging service provider
ActivityLog::register('ops', OpsLogAdapter::class);

ActivityLog::ops()
    ->crawlStarted($siteId, $runId)
    ->queue('logging')
    ->dispatch();
```

Promote frequently filtered fields in config:

```php
'promoted_fields' => [
    'site_id' => 'properties.site_id',
    'crawl_run_id' => 'properties.crawl_run_id',
],
```

Run `php artisan activity-log:indexes` after changing promoted fields.

## Integration adapter (webhooks, third-party APIs)

For outbound/inbound integration attempts with HTTP metadata in `properties`:

```php
ActivityLog::register('integration', IntegrationLogAdapter::class);

ActivityLog::integration()
    ->action('webhook.delivered')
    ->level('info')
    ->properties([
        'provider' => 'stripe',
        'http_status' => 200,
        'duration_ms' => 45,
    ])
    ->withRequest()
    ->save();
```

Use `actionPrefix('webhook.')` when reporting or pruning integration traffic.

## Security adapter

Use the built-in `security` adapter for auth events. Extend only when you need
app-specific security actions:

```php
ActivityLog::security()
    ->action('api_key.revoked')
    ->by($admin)
    ->properties(['key_id' => $keyId])
    ->save();
```

## Multi-tenant SaaS

Set `tenantId()` on any adapter before `save()` or `dispatch()`:

```php
ActivityLog::activity()
    ->action('billing.invoice.paid')
    ->tenantId($tenant->id)
    ->by($user)
    ->save();
```

Query with `ActivityLog::query()->tenantId($tenantId)`.

## Async vs sync

| API | Behavior |
|-----|----------|
| `save()` | Synchronous MongoDB write; returns `ActivityLogRecord` |
| `queue('name')->dispatch()` | Pushes `StoreActivityLogJob`; listen for `ActivityLogStoreFailed` on persistent failures |
| `sync()->dispatch()` | Immediate write via dispatch-style API |

## What stays in the app

Keep domain classifiers, trace assemblers, and dashboard UI in the application.
The package provides storage, query, retention, and sanitization — not
crawl-specific or product-specific logic.
