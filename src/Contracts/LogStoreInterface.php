<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

interface LogStoreInterface
{
    public function record(ActivityLogData $data): ActivityLogRecord;

    /**
     * @param  list<ActivityLogData>  $records
     * @return Collection<int, ActivityLogRecord>
     */
    public function recordMany(array $records): Collection;
}
