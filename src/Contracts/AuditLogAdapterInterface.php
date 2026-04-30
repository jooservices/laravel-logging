<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

interface AuditLogAdapterInterface extends LogAdapterInterface
{
    public function created(Model $model): static;

    public function updated(Model $model): static;

    public function deleted(Model $model): static;

    public function restored(Model $model): static;

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>|JsonSerializable  $changes
     */
    public function changes(array|Arrayable|JsonSerializable $changes): static;

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $before
     * @param  array<string, mixed>|Arrayable<string, mixed>  $after
     */
    public function changesFrom(array|Arrayable $before, array|Arrayable $after): static;

    /**
     * @param  array<int, string>  $fields
     */
    public function only(array $fields): static;

    /**
     * @param  array<int, string>  $fields
     */
    public function except(array $fields): static;

    public function logOnlyDirty(bool $enabled = true): static;
}
