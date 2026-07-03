<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Events;

use JOOservices\LaravelLogging\DTO\ActivityLogData;
use Throwable;

final class ActivityLogStoreFailed
{
    public function __construct(
        public readonly ActivityLogData $data,
        public readonly ?Throwable $exception,
    ) {}
}
