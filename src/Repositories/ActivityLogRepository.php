<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Support\PromotedFieldPromoter;
use JOOservices\LaravelRepository\Contracts\CrudRepositoryInterface;
use JOOservices\LaravelRepository\Repositories\EloquentRepository;
use JOOservices\LaravelRepository\Traits\HasCrud;
use JOOservices\LaravelRepository\Traits\HasRead;

/**
 * Internal persistence repository for activity log records.
 */
final class ActivityLogRepository extends EloquentRepository implements CrudRepositoryInterface
{
    use HasCrud;
    use HasRead;

    public function __construct(ActivityLogRecord $model)
    {
        parent::__construct($model);
    }

    public function record(ActivityLogData $data): ActivityLogRecord
    {
        $attributes = PromotedFieldPromoter::apply($data->toPersistenceArray());
        $record = $this->create($attributes);

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
        /** @var Collection<int, ActivityLogRecord> $created */
        $created = new Collection();

        foreach ($records as $data) {
            $created->push($this->record($data));
        }

        return $created;
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
