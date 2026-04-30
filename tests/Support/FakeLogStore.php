<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Support;

use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

final class FakeLogStore implements LogStoreInterface
{
    /** @var array<int, ActivityLogData> */
    public array $records = [];

    public function record(ActivityLogData $data): ActivityLogRecord
    {
        $this->records[] = $data;

        $record = new ActivityLogRecord;
        $record->forceFill($data->toPersistenceArray());
        $record->exists = true;
        $record->syncOriginal();

        return $record;
    }
}
