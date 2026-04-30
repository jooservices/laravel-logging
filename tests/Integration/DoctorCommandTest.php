<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Integration;

use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Tests\TestCase;
use stdClass;

final class DoctorCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresMongoDb();
        $this->clearActivityLogs();
    }

    public function test_doctor_command_reports_healthy_runtime(): void
    {
        $this->artisan('activity-log:doctor')
            ->expectsOutputToContain('config')
            ->expectsOutputToContain('repository')
            ->expectsOutputToContain('store')
            ->assertSuccessful();
    }

    public function test_doctor_command_reports_invalid_adapter_resolution(): void
    {
        /** @var LogAdapterRegistryInterface $registry */
        $registry = $this->app->make(LogAdapterRegistryInterface::class);
        $registry->replace('activity', fn (): stdClass => new stdClass);

        $this->artisan('activity-log:doctor')
            ->expectsOutputToContain('adapter:activity')
            ->assertFailed();
    }

    public function test_doctor_command_reports_invalid_store_binding(): void
    {
        $this->app->bind(LogStoreInterface::class, fn (): stdClass => new stdClass);

        $this->artisan('activity-log:doctor')
            ->expectsOutputToContain('store')
            ->assertFailed();
    }

    public function test_doctor_command_can_render_json_output(): void
    {
        $this->artisan('activity-log:doctor', ['--json' => true])
            ->expectsOutputToContain('"checks"')
            ->assertSuccessful();
    }
}
