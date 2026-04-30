<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

interface LogStoreInterface
{
    public function record(ActivityLogData $data): ActivityLogRecord;
}
