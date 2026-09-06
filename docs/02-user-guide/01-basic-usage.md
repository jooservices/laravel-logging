# Basic Usage

Use the facade for normal application code:

```php
use JOOservices\LaravelLogging\Facades\ActivityLog;

ActivityLog::activity()
    ->action('provider.disabled')
    ->by($user)
    ->on($provider)
    ->save();
```

Built-in adapters cover common record types:

```php
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

ActivityLog::domain()
    ->fromEvent($event)
    ->save();

ActivityLog::system()
    ->commandStarted('crawler:run')
    ->context(['provider' => 'onejav'])
    ->save();
```

String actor, subject, and causer inputs are named identifiers only. They are
not parsed for delimiters or inferred IDs.

```php
ActivityLog::activity()->by('system');
// actor_type = system, actor_id = null

ActivityLog::activity()->onExternal('provider', 123);
// subject_type = provider, subject_id = 123
```

Sensitive keys are recursively redacted from `properties`, `context`, and
`changes` via store `prepare()` (suffix/exact denylist plus optional value
patterns). Adapter `toData()` stays raw. `save()` and `dispatch()` (including
queued jobs) prepare before persist or enqueue so queue payloads are already
redacted.

Do not reuse an adapter instance after `save()` or `dispatch()`. Those methods
reset mutable builder state. Prefer a fresh facade/manager resolve per write:

```php
ActivityLog::activity()->action('one')->save();
ActivityLog::activity()->action('two')->save();
```
