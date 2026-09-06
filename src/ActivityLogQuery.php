<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use JOOservices\LaravelLogging\Support\ActivityLogAggregator;
use JOOservices\LaravelLogging\Support\LogIdentity;

/**
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final class ActivityLogQuery
{
    /** @var Builder<Model> */
    private Builder $builder;

    public function __construct(ActivityLogRepository $repository)
    {
        $this->builder = $repository->newQuery();
    }

    public function __clone(): void
    {
        $this->builder = clone $this->builder;
    }

    public function type(string | BackedEnum $type): self
    {
        return $this->whereEnum('type', $type);
    }

    public function adapter(string | BackedEnum $adapter): self
    {
        return $this->whereEnum('adapter', $adapter);
    }

    public function level(string | BackedEnum $level): self
    {
        return $this->whereEnum('level', $level);
    }

    public function action(string | BackedEnum $action): self
    {
        return $this->whereEnum('action', $action);
    }

    public function forSubject(Model | string $subject, string | int | null $id = null): self
    {
        $identity = LogIdentity::subject($subject, $id);

        return $this->identity('subject', $identity['type'], $identity['id']);
    }

    public function byActor(Model | Authenticatable | string $actor, string | int | null $id = null): self
    {
        $identity = LogIdentity::actor($actor, $id);

        return $this->identity('actor', $identity['type'], $identity['id']);
    }

    public function causedBy(Model | Authenticatable | string $causer, string | int | null $id = null): self
    {
        $identity = LogIdentity::actor($causer, $id);

        return $this->identity('causer', $identity['type'], $identity['id']);
    }

    public function correlationId(string $correlationId): self
    {
        return $this->where('correlation_id', $correlationId);
    }

    public function requestId(string $requestId): self
    {
        return $this->where('request_id', $requestId);
    }

    public function traceId(string $traceId): self
    {
        return $this->where('trace_id', $traceId);
    }

    public function tenantId(string | int $tenantId): self
    {
        return $this->where('tenant_id', (string) $tenantId);
    }

    public function actionPrefix(string $prefix): self
    {
        $this->builder->where('action', 'like', $prefix . '%');

        return $this;
    }

    public function batchId(string | int $batchId): self
    {
        return $this->where('batch_id', (string) $batchId);
    }

    public function workflowId(string | int $workflowId): self
    {
        return $this->where('workflow_id', (string) $workflowId);
    }

    /**
     * Filter by a promoted top-level field configured in laravel-logging.promoted_fields.
     */
    public function wherePromoted(string $field, mixed $value): self
    {
        return $this->where($field, $value);
    }

    /**
     * Jump to records sharing correlation_id or batch_id with $record.
     */
    public function relatedTo(ActivityLogRecord $record): self
    {
        $correlationId = $record->correlation_id;
        $batchId = $record->getAttribute('batch_id');
        if (! is_string($batchId) || $batchId === '') {
            $nested = data_get($record->context, 'batch_id');
            $batchId = is_string($nested) ? $nested : null;
        }

        if (is_string($correlationId) && $correlationId !== '') {
            $this->builder->where('correlation_id', $correlationId);

            return $this;
        }

        if (is_string($batchId) && $batchId !== '') {
            return $this->batchId($batchId);
        }

        $this->builder->where('_id', $record->getKey());

        return $this;
    }

    public function between(DateTimeInterface | string $from, DateTimeInterface | string $to): self
    {
        $this->builder->where('occurred_at', '>=', $this->date($from));
        $this->builder->where('occurred_at', '<=', $this->date($to));

        return $this;
    }

    public function since(DateTimeInterface | string $from): self
    {
        return $this->where('occurred_at', '>=', $this->date($from));
    }

    public function until(DateTimeInterface | string $to): self
    {
        return $this->where('occurred_at', '<=', $this->date($to));
    }

    public function latest(): self
    {
        $this->builder->latest('occurred_at');

        return $this;
    }

    public function oldest(): self
    {
        $this->builder->oldest('occurred_at');

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->builder->getQuery()->limit($limit);

        return $this;
    }

    /**
     * @return Collection<int, ActivityLogRecord>
     */
    public function get(): Collection
    {
        /** @var Collection<int, ActivityLogRecord> */
        return $this->builder->get();
    }

    public function first(): ?ActivityLogRecord
    {
        $record = $this->builder->first();

        return $record instanceof ActivityLogRecord ? $record : null;
    }

    public function latestRecord(): ?ActivityLogRecord
    {
        return (clone $this)->latest()->first();
    }

    public function previousRecord(): ?ActivityLogRecord
    {
        $latest = $this->latestRecord();

        if ($latest === null) {
            return null;
        }

        $occurredAt = $latest->occurred_at;

        if ($occurredAt === null) {
            return null;
        }

        return (clone $this)
            ->where('occurred_at', '<', $occurredAt)
            ->latest()
            ->first();
    }

    /**
     * @return array<string, int>
     */
    public function countByAction(): array
    {
        return (new ActivityLogAggregator($this->builder))->countByAction();
    }

    /**
     * @return array<string, int>
     */
    public function countByLevel(): array
    {
        return (new ActivityLogAggregator($this->builder))->countByLevel();
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->builder->paginate($perPage);
    }

    /**
     * @param  callable(ActivityLogRecord): void  $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->builder->cursor() as $record) {
            if ($record instanceof ActivityLogRecord) {
                $callback($record);
            }
        }
    }

    private function whereEnum(string $field, string | BackedEnum $value): self
    {
        return $this->where($field, $value instanceof BackedEnum ? (string) $value->value : $value);
    }

    private function where(string $field, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->builder->where($field, $operator);

            return $this;
        }

        $this->builder->where($field, $operator, $value);

        return $this;
    }

    private function identity(string $prefix, ?string $type, ?string $id): self
    {
        $this->builder->where("{$prefix}_type", $type);
        $this->builder->where("{$prefix}_id", $id);

        return $this;
    }

    private function date(DateTimeInterface | string $date): DateTimeInterface
    {
        return is_string($date) ? CarbonImmutable::parse($date) : $date;
    }
}
