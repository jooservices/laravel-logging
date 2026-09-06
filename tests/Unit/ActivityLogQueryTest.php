<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use InvalidArgumentException;
use JOOservices\LaravelLogging\ActivityLogQuery;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Tests\TestCase;

final class ActivityLogQueryTest extends TestCase
{
    public function test_where_promoted_rejects_unconfigured_field(): void
    {
        config()->set('laravel-logging.promoted_fields', ['batch_id' => 'context.batch_id']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Promoted field [unknown]');

        ActivityLog::query()->wherePromoted('unknown', 'x');
    }

    public function test_since_returns_query_builder(): void
    {
        $query = ActivityLog::query()->since('2026-01-01T00:00:00+00:00');

        $this->assertInstanceOf(ActivityLogQuery::class, $query);
    }
}
