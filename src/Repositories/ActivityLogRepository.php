<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Repositories;

use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use Jooservices\LaravelRepository\Contracts\CrudRepositoryInterface;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;

final class ActivityLogRepository extends EloquentRepository implements CrudRepositoryInterface
{
    use HasCrud;

    public function __construct()
    {
        parent::__construct(new ActivityLogRecord);
    }

    public function record(ActivityLogData $data): ActivityLogRecord
    {
        $record = $this->create($data->toPersistenceArray());

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
