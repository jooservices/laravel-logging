# Cookbook

Practical patterns for `jooservices/laravel-logging`.

## Audit a model (opt-in)

```php
use JOOservices\LaravelLogging\Concerns\LogsActivity;
use JOOservices\LaravelLogging\ActivityLogOptions;

final class Provider extends Model
{
    use LogsActivity;

    protected function activityLogOptions(): ActivityLogOptions
    {
        return ActivityLogOptions::make()
            ->logOnly(['name', 'status'])
            ->logOnlyDirty();
    }
}
```

## Queue non-blocking writes

```php
use JOOservices\LaravelLogging\Facades\ActivityLog;

ActivityLog::system()
    ->action('crawler.run.started')
    ->context(['provider' => 'onejav'])
    ->queue()
    ->dispatch();
```

## Custom adapter

```bash
php artisan make:log-adapter OpsAdapter --type=ops
```

Register in `config/laravel-logging.php`:

```php
'adapters' => [
    // ...
    'ops' => \App\Logging\Adapters\OpsAdapter::class,
],
```

## Bulk persistence

```php
use JOOservices\LaravelLogging\Facades\ActivityLog;

$records = [
    ActivityLog::system()->action('bulk.one')->toData(),
    ActivityLog::system()->action('bulk.two')->toData(),
];

ActivityLog::recordMany($records);
```

## Query helpers

```php
ActivityLog::query()
    ->batchId($batchId)
    ->workflowId($workflowId)
    ->relatedTo($seedRecord)
    ->between('2026-01-01', '2026-01-31')
    ->latest()
    ->paginate(50);
```

## Retention and export

```bash
php artisan activity-log:prune --dry-run
php artisan activity-log:prune --force
php artisan activity-log:export --type=audit --format=jsonl --output=storage/app/audit.jsonl
```

## Optional TTL index

```env
ACTIVITY_LOG_TTL_ENABLED=true
ACTIVITY_LOG_TTL_DAYS=365
```

```bash
php artisan activity-log:indexes
```

Prefer typed prune for per-adapter retention; TTL is a coarse collection ceiling.

## HTTP middleware (opt-in)

```env
ACTIVITY_LOG_HTTP_ENABLED=true
```

Register `JOOservices\LaravelLogging\Http\Middleware\LogHttpRequest` in the
application middleware stack.

## Doctor

```bash
php artisan activity-log:doctor --json
php artisan activity-log:doctor --check-indexes --strict
```
