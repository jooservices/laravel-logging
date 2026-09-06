<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Repositories;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Support\ActivityLogAggregator;
use JOOservices\LaravelLogging\Support\PromotedFieldPromoter;
use JOOservices\LaravelRepository\Contracts\CrudRepositoryInterface;
use JOOservices\LaravelRepository\Contracts\FilterInterface;
use JOOservices\LaravelRepository\Repositories\EloquentRepository;
use JOOservices\LaravelRepository\Support\Filter;
use JOOservices\LaravelRepository\Traits\HasCrud;
use JOOservices\LaravelRepository\Traits\HasFilter;
use JOOservices\LaravelRepository\Traits\HasIteration;
use JOOservices\LaravelRepository\Traits\HasOrder;
use JOOservices\LaravelRepository\Traits\HasRead;
use MongoDB\Driver\Exception\BulkWriteException;
use Throwable;

/**
 * Internal persistence repository for activity log records.
 *
 * Not a public extension point — use ActivityLog::query() / adapters / store.
 */
final class ActivityLogRepository extends EloquentRepository implements CrudRepositoryInterface
{
    use HasCrud;
    use HasFilter;
    use HasIteration;
    use HasOrder;
    use HasRead;

    public function __construct(ActivityLogRecord $model)
    {
        parent::__construct($model);
    }

    /**
     * Fresh instance so HasFilter/HasOrder state never leaks across callers.
     */
    public function fresh(): self
    {
        $model = $this->getModel();

        return new self($model instanceof ActivityLogRecord ? $model : new ActivityLogRecord());
    }

    public function limit(int $limit): static
    {
        $this->getQuery()->limit($limit);

        return $this;
    }

    public function record(ActivityLogData $data): ActivityLogRecord
    {
        $existing = $this->findBy(['uuid' => $data->uuid]);

        if ($existing instanceof ActivityLogRecord) {
            return $existing;
        }

        $attributes = PromotedFieldPromoter::apply($data->toArray());

        try {
            $record = $this->create($attributes);
        } catch (BulkWriteException $exception) {
            $existing = $this->findBy(['uuid' => $data->uuid]);

            if ($existing instanceof ActivityLogRecord) {
                return $existing;
            }

            throw $exception;
        } catch (Throwable $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            $existing = $this->findBy(['uuid' => $data->uuid]);

            if ($existing instanceof ActivityLogRecord) {
                return $existing;
            }

            throw $exception;
        }

        if (! $record instanceof ActivityLogRecord) {
            return $this->hydrateRecord($record);
        }

        return $record;
    }

    /**
     * @param  list<ActivityLogData>  $records
     * @return Collection<int, ActivityLogRecord>
     */
    public function recordMany(array $records): Collection
    {
        if ($records === []) {
            return new Collection();
        }

        $rows = [];
        $uuids = [];

        foreach ($records as $data) {
            $rows[] = PromotedFieldPromoter::apply($data->toArray());
            $uuids[] = $data->uuid;
        }

        $this->upsert($rows, ['uuid']);

        /** @var Collection<string, ActivityLogRecord> $byUuid */
        $byUuid = $this->getModel()
            ->newQuery()
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');

        /** @var Collection<int, ActivityLogRecord> $ordered */
        $ordered = new Collection();

        foreach ($uuids as $uuid) {
            $record = $byUuid->get($uuid);

            if ($record instanceof ActivityLogRecord) {
                $ordered->push($record);
            }
        }

        return $ordered;
    }

    /**
     * Delete matching rows with a single filtered deleteMany (no hydration).
     *
     * @param  iterable<int, FilterInterface>|iterable<string, mixed>  $filters
     * @param  int  $chunkSize  Kept for call-site compatibility; unused for deleteMany.
     * @return array{matched: int, deleted: int}
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function pruneMatching(iterable $filters, int $chunkSize = 500, bool $delete = false): array
    {
        $matched = $this->fresh()->filter($filters)->count();

        if ($delete === false || $matched === 0) {
            return ['matched' => $matched, 'deleted' => 0];
        }

        $repo = $this->fresh()->filter($filters);

        try {
            $rawDeleted = $repo->getQuery()->delete();
            $deleted = is_int($rawDeleted)
                ? $rawDeleted
                : (is_numeric($rawDeleted) ? (int) $rawDeleted : 0);
        } finally {
            $repo->query = null;
        }

        return ['matched' => $matched, 'deleted' => max(0, $deleted)];
    }

    /**
     * Stream matching rows without offset pagination (cursor batches).
     *
     * @param  iterable<int, FilterInterface>|iterable<string, mixed>  $filters
     * @param  Closure(Collection<int, Model>, int): mixed  $callback
     */
    public function exportChunk(iterable $filters, int $chunkSize, Closure $callback, string $direction = 'asc'): bool
    {
        $chunkSize = max(1, $chunkSize);
        $repo = $this->fresh()->filter($filters)->orderBy(['occurred_at' => $direction, '_id' => $direction]);
        $batch = new Collection();
        $page = 0;

        foreach ($repo->cursor() as $record) {
            $batch->push($record);

            if ($batch->count() >= $chunkSize) {
                $callback($batch, ++$page);
                $batch = new Collection();
            }
        }

        if ($batch->count() > 0) {
            $callback($batch, ++$page);
        }

        return true;
    }

    /**
     * @param  iterable<int, FilterInterface>|iterable<string, mixed>  $filters
     * @return array<string, int>
     */
    public function countGroupedBy(string $field, iterable $filters = []): array
    {
        $repo = $this->fresh();

        if ($filters !== []) {
            $repo->filter($filters);
        }

        try {
            return (new ActivityLogAggregator($repo->getQuery()))->countByField($field);
        } finally {
            $repo->query = null;
        }
    }

    /**
     * @return list<FilterInterface>
     */
    public static function beforeOccurredAt(DateTimeInterface $cutoff, ?string $type = null): array
    {
        $filters = [new Filter('occurred_at', $cutoff, 'before')];

        if ($type !== null && $type !== '') {
            $filters[] = new Filter('type', $type);
        }

        return $filters;
    }

    private function isDuplicateKey(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'E11000')
            || str_contains(strtolower($message), 'duplicate key');
    }

    private function hydrateRecord(Model $model): ActivityLogRecord
    {
        $record = new ActivityLogRecord();
        $record->forceFill($model->getAttributes());
        $record->exists = $model->exists;
        $record->syncOriginal();

        return $record;
    }
}
