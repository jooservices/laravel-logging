<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Repositories;

use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Support\PromotedFieldPromoter;
use JOOservices\LaravelRepository\Contracts\CrudRepositoryInterface;
use JOOservices\LaravelRepository\Repositories\EloquentRepository;
use JOOservices\LaravelRepository\Traits\HasCrud;

final class ActivityLogRepository extends EloquentRepository implements CrudRepositoryInterface
{
    use HasCrud;

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

    private function hydrateRecord(Model $model): ActivityLogRecord
    {
        $record = new ActivityLogRecord;
        $record->forceFill($model->getAttributes());
        $record->exists = $model->exists;
        $record->syncOriginal();

        return $record;
    }
}
