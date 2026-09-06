<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\Contracts\AuditLogAdapterInterface;
use JsonSerializable;

final class AuditLogAdapter extends BaseLogAdapter implements AuditLogAdapterInterface
{
    protected string $type = 'audit';

    protected string $adapter = 'audit';

    protected ?string $level = 'info';

    /** @var array<int, string>|null */
    private ?array $only = null;

    /** @var array<int, string> */
    private array $except = [];

    private bool $logOnlyDirty = true;

    public function created(Model $model): static
    {
        return $this->action('created')->on($model)->changes(['after' => $model->getAttributes()]);
    }

    public function updated(Model $model): static
    {
        return $this->action('updated')->on($model)->changesFrom($model->getOriginal(), $model->getAttributes());
    }

    public function deleted(Model $model): static
    {
        return $this->action('deleted')->on($model)->changes(['before' => $model->getOriginal()]);
    }

    public function restored(Model $model): static
    {
        return $this->action('restored')->on($model)->changes(['after' => $model->getAttributes()]);
    }

    public function changes(array | Arrayable | JsonSerializable $changes): static
    {
        $this->setChanges($this->payloadToArray($changes));

        return $this;
    }

    public function changesFrom(array | Arrayable $before, array | Arrayable $after): static
    {
        $before = $this->payloadToArray($before);
        $after = $this->payloadToArray($after);
        $fields = array_unique([...array_keys($before), ...array_keys($after)]);
        $changes = [];

        foreach ($fields as $field) {
            if ($this->only !== null && ! in_array($field, $this->only, true)) {
                continue;
            }

            if (in_array($field, $this->except, true)) {
                continue;
            }

            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($this->logOnlyDirty && $old === $new) {
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $new];
        }

        $this->setChanges($changes);

        return $this;
    }

    public function only(array $fields): static
    {
        $this->only = array_values($fields);

        return $this;
    }

    public function except(array $fields): static
    {
        $this->except = array_values($fields);

        return $this;
    }

    public function logOnlyDirty(bool $enabled = true): static
    {
        $this->logOnlyDirty = $enabled;

        return $this;
    }
}
