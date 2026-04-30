<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JsonSerializable;

interface LogAdapterInterface
{
    public function type(string|BackedEnum $type): static;

    public function level(string|BackedEnum $level): static;

    public function action(string|BackedEnum $action): static;

    public function message(?string $message): static;

    public function by(Model|Authenticatable|string|null $actor): static;

    public function byExternal(string $type, string|int|null $id = null): static;

    public function bySystem(): static;

    public function byGuest(): static;

    public function on(Model|string|null $subject): static;

    public function onExternal(string $type, string|int|null $id = null): static;

    public function causedBy(Model|Authenticatable|string|null $causer): static;

    public function causedByExternal(string $type, string|int|null $id = null): static;

    public function source(string|BackedEnum|null $source): static;

    public function sourceType(string|BackedEnum|null $sourceType): static;

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>|JsonSerializable  $properties
     */
    public function properties(array|Arrayable|JsonSerializable $properties): static;

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>|JsonSerializable  $context
     */
    public function context(array|Arrayable|JsonSerializable $context): static;

    public function withRequest(?Request $request = null): static;

    public function correlationId(?string $correlationId): static;

    public function requestId(?string $requestId): static;

    public function traceId(?string $traceId): static;

    public function occurredAt(DateTimeInterface|string|null $occurredAt): static;

    public function sync(): static;

    public function queue(?string $queue = null): static;

    public function toData(): ActivityLogData;

    public function save(): ActivityLogRecord;
}
