<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Stores;

use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;

final class MongoLogStore implements LogStoreInterface
{
    public function __construct(private readonly ActivityLogRepository $repository)
    {
    }

    public function record(ActivityLogData $data): ActivityLogRecord
    {
        return $this->repository->record($data);
    }

    /**
     * @param  list<ActivityLogData>  $records
     * @return Collection<int, ActivityLogRecord>
     */
    public function recordMany(array $records): Collection
    {
        return $this->repository->recordMany($records);
    }
}
