<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use JOOservices\LaravelLogging\Support\LogIdentity;
use JOOservices\LaravelRepository\Contracts\FilterInterface;
use JOOservices\LaravelRepository\Support\Filter;

/**
 * Public fluent query API. Owns filter/order state and applies them to a fresh
 * ActivityLogRepository on each terminal — never leaks Eloquent Builder.
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final class ActivityLogQuery
{
    /** @var list<FilterInterface> */
    private array $filters = [];

    /** @var array<string, string> */
    private array $orders = [];

    private ?int $limit = null;

    public function __construct(private readonly ActivityLogRepository $repository)
    {
    }

    public function type(string | BackedEnum $type): self
    {
        return $this->push(new Filter('type', $this->enumValue($type)));
    }

    public function adapter(string | BackedEnum $adapter): self
    {
        return $this->push(new Filter('adapter', $this->enumValue($adapter)));
    }

    public function level(string | BackedEnum $level): self
    {
        return $this->push(new Filter('level', $this->enumValue($level)));
    }

    public function action(string | BackedEnum $action): self
    {
        return $this->push(new Filter('action', $this->enumValue($action)));
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
        return $this->push(new Filter('correlation_id', $correlationId));
    }

    public function requestId(string $requestId): self
    {
        return $this->push(new Filter('request_id', $requestId));
    }

    public function traceId(string $traceId): self
    {
        return $this->push(new Filter('trace_id', $traceId));
    }

    public function tenantId(string | int $tenantId): self
    {
        return $this->push(new Filter('tenant_id', (string) $tenantId));
    }

    public function actionPrefix(string $prefix): self
    {
        return $this->push(new Filter('action', $prefix, 'beginsWith'));
    }

    public function batchId(string | int $batchId): self
    {
        return $this->push(new Filter('batch_id', (string) $batchId));
    }

    public function workflowId(string | int $workflowId): self
    {
        return $this->push(new Filter('workflow_id', (string) $workflowId));
    }

    /**
     * Filter by a promoted top-level field configured in laravel-logging.promoted_fields.
     */
    public function wherePromoted(string $field, mixed $value): self
    {
        /** @var array<string, string> $mappings */
        $mappings = (array) config('laravel-logging.promoted_fields', []);

        if (! array_key_exists($field, $mappings)) {
            throw new InvalidArgumentException(
                "Promoted field [{$field}] is not configured in laravel-logging.promoted_fields.",
            );
        }

        return $this->push(new Filter($field, $value));
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
            return $this->correlationId($correlationId);
        }

        if (is_string($batchId) && $batchId !== '') {
            return $this->batchId($batchId);
        }

        return $this->push(new Filter('_id', $record->getKey()));
    }

    public function between(DateTimeInterface | string $from, DateTimeInterface | string $to): self
    {
        return $this
            ->push(new Filter('occurred_at', $this->date($from), 'gte'))
            ->push(new Filter('occurred_at', $this->date($to), 'lte'));
    }

    public function since(DateTimeInterface | string $from): self
    {
        return $this->push(new Filter('occurred_at', $this->date($from), 'gte'));
    }

    public function until(DateTimeInterface | string $to): self
    {
        return $this->push(new Filter('occurred_at', $this->date($to), 'lte'));
    }

    public function latest(): self
    {
        $this->orders = ['occurred_at' => 'desc', '_id' => 'desc'];

        return $this;
    }

    public function oldest(): self
    {
        $this->orders = ['occurred_at' => 'asc', '_id' => 'asc'];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return Collection<int, ActivityLogRecord>
     */
    public function get(): Collection
    {
        /** @var Collection<int, ActivityLogRecord> */
        return $this->apply()->get();
    }

    public function first(): ?ActivityLogRecord
    {
        $record = $this->apply()->first();

        return $record instanceof ActivityLogRecord ? $record : null;
    }

    public function latestRecord(): ?ActivityLogRecord
    {
        return (clone $this)->latest()->first();
    }

    public function previousRecord(): ?ActivityLogRecord
    {
        $records = (clone $this)->latest()->limit(2)->get();
        $previous = $records->get(1);

        return $previous instanceof ActivityLogRecord ? $previous : null;
    }

    /**
     * @return array<string, int>
     */
    public function countByAction(): array
    {
        return $this->repository->countGroupedBy('action', $this->filters);
    }

    /**
     * @return array<string, int>
     */
    public function countByLevel(): array
    {
        return $this->repository->countGroupedBy('level', $this->filters);
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->apply()->paginate($perPage);
    }

    /**
     * @param  callable(ActivityLogRecord): void  $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->apply()->cursor() as $record) {
            if ($record instanceof ActivityLogRecord) {
                $callback($record);
            }
        }
    }

    private function push(FilterInterface $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    private function apply(): ActivityLogRepository
    {
        $repo = $this->repository->fresh();

        if ($this->filters !== []) {
            $repo->filter($this->filters);
        }

        if ($this->orders !== []) {
            $repo->orderBy($this->orders);
        }

        if ($this->limit !== null) {
            $repo->limit($this->limit);
        }

        return $repo;
    }

    private function identity(string $prefix, ?string $type, ?string $id): self
    {
        return $this
            ->push(new Filter("{$prefix}_type", $type))
            ->push(new Filter("{$prefix}_id", $id));
    }

    private function enumValue(string | BackedEnum $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }

    private function date(DateTimeInterface | string $date): DateTimeInterface
    {
        return is_string($date) ? CarbonImmutable::parse($date) : $date;
    }
}
