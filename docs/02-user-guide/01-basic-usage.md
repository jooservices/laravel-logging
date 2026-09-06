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
`changes` using exact key matching.
