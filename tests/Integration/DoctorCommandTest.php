<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Integration;

use Illuminate\Support\Facades\Artisan;
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
        $exitCode = Artisan::call('activity-log:doctor', ['--json' => true]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('status', $payload);
        $this->assertArrayHasKey('checks', $payload);
    }

    public function test_doctor_command_can_check_indexes(): void
    {
        $this->artisan('activity-log:indexes')->assertSuccessful();

        $this->artisan('activity-log:doctor', ['--check-indexes' => true])
            ->expectsOutputToContain('indexes')
            ->assertSuccessful();
    }

    public function test_doctor_command_reports_invalid_payload_limit_config(): void
    {
        $this->app['config']->set('laravel-logging.limits.max_string_length', 0);

        $this->artisan('activity-log:doctor')
            ->expectsOutputToContain('limits')
            ->assertFailed();
    }

    public function test_doctor_command_reports_invalid_sanitizer_config(): void
    {
        $this->app['config']->set('laravel-logging.sanitization.sensitive_keys', ['']);

        $this->artisan('activity-log:doctor')
            ->expectsOutputToContain('sanitize')
            ->assertFailed();
    }

    public function test_doctor_command_reports_invalid_retention_config(): void
    {
        $this->app['config']->set('laravel-logging.retention.types', ['activity' => 0]);

        $this->artisan('activity-log:doctor')
            ->expectsOutputToContain('retention')
            ->assertFailed();
    }

    public function test_doctor_command_strict_mode_fails_on_index_warnings(): void
    {
        $this->app['config']->set('laravel-logging.collection', 'activity_logs_strict_doctor');
        $this->clearCollection('activity_logs_strict_doctor');

        $this->artisan('activity-log:doctor', ['--check-indexes' => true, '--strict' => true])
            ->assertFailed();
    }
}
