# Model Auditing And Domain Mappers

## Model Auditing

Model audit logging is opt-in only. Add `LogsActivity` to models that should
emit audit logs.

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

The trait records created, updated, deleted, and restored events. Sanitization
still applies to changes before storage.

## Domain Mappers

Domain mappers customize `domain()->fromEvent($event)` without replacing the
domain adapter.

```php
final class InvoicePaidMapper implements DomainLogMapperInterface
{
    public function supports(object $event): bool
    {
        return $event instanceof InvoicePaid;
    }

    public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface
    {
        return $adapter
            ->action('domain.invoice.paid')
            ->onExternal('invoice', $event->invoiceId)
            ->properties(['total' => $event->total]);
    }
}
```

Register mapper classes in `config/laravel-logging.php` under
`domain_mappers`.
