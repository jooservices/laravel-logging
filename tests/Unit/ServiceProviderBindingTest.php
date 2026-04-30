<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use JOOservices\LaravelLogging\Tests\Stubs\CustomActivityLogRecord;
use JOOservices\LaravelLogging\Tests\TestCase;

final class ServiceProviderBindingTest extends TestCase
{
    public function test_repository_uses_configured_activity_log_model_binding(): void
    {
        $this->app['config']->set('laravel-logging.model', CustomActivityLogRecord::class);
        $this->app->forgetInstance(ActivityLogRecord::class);
        $this->app->forgetInstance(ActivityLogRepository::class);

        $repository = $this->app->make(ActivityLogRepository::class);

        $this->assertInstanceOf(CustomActivityLogRecord::class, $repository->getModel());
    }
}
