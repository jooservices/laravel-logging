<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\Contracts\AuditLogAdapterInterface;
use JOOservices\LaravelLogging\Support\AuditableAttributes;
use JsonSerializable;

final class AuditLogAdapter extends BaseLogAdapter implements AuditLogAdapterInterface
{
    protected string $type = 'audit';

    protected string $adapter = 'audit';

    protected ?string $level = 'info';

    /** @var array<int, string>|null */
    private ?array $only = null;

    /** @var array<int, string>|null */
    private ?array $except = null;

    private bool $logOnlyDirty = true;

    public function created(Model $model): static
    {
        return $this->action('created')->on($model)->changes([
            'after' => $this->filteredModelAttributes($model),
        ]);
    }

    public function updated(Model $model): static
    {
        return $this->action('updated')->on($model)->changesFrom(
            $this->filteredModelAttributes($model, $model->getOriginal()),
            $this->filteredModelAttributes($model),
        );
    }

    public function deleted(Model $model): static
    {
        return $this->action('deleted')->on($model)->changes([
            'before' => $this->filteredModelAttributes($model, $model->getOriginal()),
        ]);
    }

    public function restored(Model $model): static
    {
        return $this->action('restored')->on($model)->changes([
            'after' => $this->filteredModelAttributes($model),
        ]);
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
        $except = $this->effectiveExcept();

        foreach ($fields as $field) {
            if ($this->only !== null && ! in_array($field, $this->only, true)) {
                continue;
            }

            if (in_array($field, $except, true)) {
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
        $this->except = array_values(array_unique([...AuditableAttributes::defaultExcept(), ...$fields]));

        return $this;
    }

    public function logOnlyDirty(bool $enabled = true): static
    {
        $this->logOnlyDirty = $enabled;

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>
     */
    private function filteredModelAttributes(Model $model, ?array $attributes = null): array
    {
        return AuditableAttributes::filter(
            $attributes ?? $model->getAttributes(),
            $this->only,
            $this->effectiveExcept(),
            array_values($model->getHidden()),
        );
    }

    /**
     * @return array<int, string>
     */
    private function effectiveExcept(): array
    {
        return $this->except ?? AuditableAttributes::defaultExcept();
    }
}
