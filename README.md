# Laravel Logging

`jooservices/laravel-logging` stores structured activity, audit, security, domain, and system logs in MongoDB for Laravel applications.

It is not a replacement for Laravel Log, Monolog, Sentry, OpenTelemetry, Loki, ELK, or a full observability stack. It is a focused package for durable structured application records.

## Requirements

- PHP 8.5+
- Laravel 12
- `mongodb/laravel-mongodb`
- `jooservices/dto`
- `jooservices/laravel-repository`
- A MongoDB connection configured in Laravel

## Installation

```bash
composer require jooservices/laravel-logging
php artisan vendor:publish --tag=laravel-logging-config
php artisan activity-log:indexes
php artisan activity-log:doctor
```

Use the doctor command to validate config loading, MongoDB reachability,
container bindings, adapter resolution, retention/export config, sanitization,
payload limits, and command registration. It does not mutate data.

```bash
php artisan activity-log:doctor --json
php artisan activity-log:doctor --check-indexes
```

The default MongoDB collection is `activity_logs`.

Configure the MongoDB connection used by the package:

```env
ACTIVITY_LOG_CONNECTION=mongodb
ACTIVITY_LOG_COLLECTION=activity_logs
MONGODB_URI=mongodb://localhost:27017
```

## Usage

```php
use JOOservices\LaravelLogging\Facades\ActivityLog;

ActivityLog::activity()
    ->action('provider.disabled')
    ->by($user)
    ->on($provider)
    ->save();

ActivityLog::audit()
    ->action('config.updated')
    ->by($user)
    ->on($config)
    ->changesFrom($before, $after)
    ->save();

ActivityLog::security()
    ->loginFailed($email)
    ->withRequest()
    ->save();

ActivityLog::system()
    ->commandStarted('crawler:run')
    ->context(['provider' => 'onejav'])
    ->save();

ActivityLog::domain()
    ->fromEvent($event)
    ->save();
```

Trace batch and workflow IDs through normal context fields:

```php
ActivityLog::activity()
    ->action('crawler.item.imported')
    ->batchId($batchId)
    ->workflowId($workflowId)
    ->save();
```

String actor, subject, and causer values are stored exactly as named identifiers. They are not parsed.

```php
ActivityLog::activity()->by('system');
// actor_type = system, actor_id = null

ActivityLog::activity()->onExternal('provider', 123);
// subject_type = provider, subject_id = 123
```

## Queueing

`save()` always writes synchronously and returns an `ActivityLogRecord`.

For async logging, use `queue()` with `dispatch()`:

```php
ActivityLog::activity()
    ->action('crawler.completed')
    ->bySystem()
    ->queue('logging')
    ->dispatch();
```

`queue()` only selects the queue target. It does not make `save()` asynchronous.

Use `sync()->dispatch()` when code needs the dispatch-style API but immediate persistence.

## Adapters

Built-in adapters are registered from config:

- `activity`
- `audit`
- `security`
- `domain`
- `system`

Custom adapters implement `LogAdapterInterface`, usually by extending `BaseLogAdapter`.
The contract includes `save(): ActivityLogRecord` for synchronous persistence and
`dispatch(): void` for dispatch-style sync or async logging.

`ActivityLogRepository` remains an internal package data-access layer. Public
repository replacement is not a supported configuration surface in v1.

```php
ActivityLog::register('crawler', CrawlerLogAdapter::class);

ActivityLog::crawler()
    ->action('crawler.completed')
    ->bySystem()
    ->properties(['items' => 120])
    ->save();
```

`ActivityLog::crawler()` is sugar for `ActivityLog::adapter('crawler')`. Magic adapter methods do not accept parameters.

## Querying

Use `query()` or `records()` for common reporting filters:

```php
$records = ActivityLog::query()
    ->type('audit')
    ->action('config.updated')
    ->forSubject($config)
    ->byActor($user)
    ->correlationId($id)
    ->between($from, $to)
    ->latest()
    ->paginate();
```

String identity filters follow logging semantics: strings are named identifiers
unless an explicit ID is provided.

## Retention And Export

Prune old records with configured per-type retention:

```bash
php artisan activity-log:prune --dry-run
php artisan activity-log:prune --force
php artisan activity-log:prune --type=system --days=30 --force
php artisan activity-log:prune --type=audit --before=2026-01-01 --force
```

Without `--force`, prune runs as a dry-run. `--days` and `--before` are mutually
exclusive, cutoffs use `occurred_at`, and future cutoffs are rejected.

Export audit or security records as JSONL or CSV:

```bash
php artisan activity-log:export --type=audit --from=2026-01-01 --format=jsonl --output=storage/app/audit.jsonl
php artisan activity-log:export --format=csv --output=storage/app/activity-logs.csv
```

JSONL includes the full stored document array. CSV exports stable top-level
fields only. Export streams records and refuses to overwrite existing files
unless `--force` is passed.

## Model Audit Logging

Model logging is opt-in only:

```php
use JOOservices\LaravelLogging\ActivityLogOptions;
use JOOservices\LaravelLogging\Concerns\LogsActivity;

class Setting extends Model
{
    use LogsActivity;

    protected function activityLogOptions(): ActivityLogOptions
    {
        return ActivityLogOptions::make()
            ->logOnly(['name', 'value', 'enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

The trait records audit logs for created, updated, deleted, and restored model
events when the model opts in.

## Domain Event Mappers

`domain()->fromEvent($event)` uses registered domain mappers first, then falls
back to the default event-class projection.

```php
ActivityLog::domain()
    ->fromEvent($event)
    ->save();
```

## Sanitization And Payload Limits

Sensitive keys are recursively redacted in `properties`, `context`, and
`changes` before payload limits are applied. Matching is exact and
case-insensitive by default. Defaults include `password`, `token`,
`access_token`, `refresh_token`, `secret`, `api_key`, `authorization`,
`cookie`, and `x-api-key`.

Payload limits truncate oversized strings, long arrays, deep nested payloads,
and oversized approximate documents before MongoDB persistence.

```php
'retention' => [
    'enabled' => true,
    'default_days' => 180,
    'chunk_size' => 500,
    'types' => [
        'activity' => 90,
        'audit' => 365,
        'security' => 365,
        'domain' => 90,
        'system' => 30,
    ],
],
'export' => [
    'chunk_size' => 500,
    'formats' => ['jsonl', 'csv'],
],
'sanitization' => [
    'enabled' => true,
    'case_sensitive' => false,
    'redacted_value' => '[redacted]',
    'sensitive_keys' => ['password', 'token', 'authorization', 'cookie'],
    'sensitive_patterns' => [],
],
'limits' => [
    'enabled' => true,
    'max_string_length' => 5000,
    'max_array_items' => 200,
    'max_depth' => 8,
    'max_document_bytes' => 524288,
    'truncate_marker' => '[truncated]',
],
```

## MongoDB Schema

Documents include classification fields, actor/subject/causer references, source and trace IDs, client context, structured `properties`, `context`, `changes`, `occurred_at`, and Laravel timestamps.

Run `php artisan activity-log:indexes` to create supported top-level indexes. Nested `properties`, `context`, and `changes` keys are not indexed in v1.

Core fields:

- `uuid`, `type`, `adapter`, `level`, `action`, `message`
- `actor_type`, `actor_id`
- `subject_type`, `subject_id`
- `causer_type`, `causer_id`
- `request_id`, `correlation_id`, `trace_id`
- `properties`, `context`, `changes`
- `occurred_at`, `created_at`, `updated_at`

## Development

```bash
composer validate --strict
composer run lint:fix
composer run lint:all
composer run test
composer run test:coverage
composer run check
composer audit
composer run ci
composer run format:sanity
```

MongoDB integration tests require a running MongoDB server at `MONGODB_URI` or `mongodb://localhost:27017`. Without MongoDB, integration tests are skipped with a clear message.

This package tests the real persistence flow. It does not mock internal store,
repository, model, adapter, or DTO layers. Allowed fakes stay at Laravel
framework boundaries such as queue dispatch, events, or temporary filesystem
output.

AI contributors should follow `AGENTS.md` and the package-specific skills in `.github/skills/`. When code, config, tooling, workflow, architecture, or behavior changes, update the matching docs and AI guidance before committing.
