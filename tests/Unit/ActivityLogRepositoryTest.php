<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use JOOservices\LaravelLogging\Tests\TestCase;

final class ActivityLogRepositoryTest extends TestCase
{
    public function test_record_many_with_empty_list_returns_empty_collection(): void
    {
        /** @var ActivityLogRepository $repository */
        $repository = $this->app->make(ActivityLogRepository::class);

        $this->assertTrue($repository->recordMany([])->isEmpty());
    }
}
