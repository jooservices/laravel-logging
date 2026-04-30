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
```

The default MongoDB collection is `activity_logs`.

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

```php
ActivityLog::register('crawler', CrawlerLogAdapter::class);

ActivityLog::crawler()
    ->action('crawler.completed')
    ->bySystem()
    ->properties(['items' => 120])
    ->save();
```

`ActivityLog::crawler()` is sugar for `ActivityLog::adapter('crawler')`. Magic adapter methods do not accept parameters.

## Sanitization

Sensitive keys are recursively redacted in `properties`, `context`, and `changes` using exact key matching. Defaults include `password`, `token`, `secret`, `api_key`, `authorization`, and `cookie`.

## MongoDB Schema

Documents include classification fields, actor/subject/causer references, source and trace IDs, client context, structured `properties`, `context`, `changes`, `occurred_at`, and Laravel timestamps.

Run `php artisan activity-log:indexes` to create supported top-level indexes. Nested `properties`, `context`, and `changes` keys are not indexed in v1.

## Development

```bash
composer validate
composer run lint:fix
composer run lint:all
composer run test
composer run check
composer audit
composer run ci
```

MongoDB integration tests require a running MongoDB server at `MONGODB_URI` or `mongodb://localhost:27017`. Without MongoDB, integration tests are skipped with a clear message.

AI contributors should follow `AGENTS.md` and the package-specific skills in `.github/skills/`. When code, config, tooling, workflow, architecture, or behavior changes, update the matching docs and AI guidance before committing.
