<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

interface LogStoreInterface
{
    /**
     * Sanitize and limit bags before persist or queue serialization.
     * Persist paths call this again; already-redacted payloads stay safe.
     */
    public function prepare(ActivityLogData $data): ActivityLogData;

    public function record(ActivityLogData $data): ActivityLogRecord;

    /**
     * @param  list<ActivityLogData>  $records
     * @return Collection<int, ActivityLogRecord>
     */
    public function recordMany(array $records): Collection;
}
