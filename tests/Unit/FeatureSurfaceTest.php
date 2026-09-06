<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use JOOservices\LaravelLogging\Console\Commands\InstallActivityLogIndexesCommand as IndexesCommand;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Http\Middleware\LogHttpRequest;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class FeatureSurfaceTest extends TestCase
{
    public function test_record_many_persists_multiple_dtos_through_store(): void
    {
        $this->requiresMongoDb();
        $this->clearActivityLogs();

        $store = $this->app->make(LogStoreInterface::class);
        $batch = 'batch-' . Str::uuid()->toString();

        $created = $store->recordMany([
            $this->makeData(action: 'bulk.one', correlationId: 'corr-1', context: ['batch_id' => $batch]),
            $this->makeData(action: 'bulk.two', correlationId: 'corr-1', context: ['batch_id' => $batch]),
        ]);

        $this->assertCount(2, $created);
        $first = $created->first();
        $this->assertInstanceOf(ActivityLogRecord::class, $first);
        $this->assertSame($batch, $first->batch_id);
        $this->assertCount(2, ActivityLog::query()->batchId($batch)->get());
        $this->assertCount(2, ActivityLog::query()->correlationId('corr-1')->get());
    }

    public function test_manager_record_many_and_query_helpers(): void
    {
        $this->requiresMongoDb();
        $this->clearActivityLogs();

        $batch = 'batch-' . Str::uuid()->toString();
        $workflow = 'wf-' . Str::uuid()->toString();

        ActivityLog::recordMany([
            $this->makeData(
                action: 'mgr.one',
                context: ['batch_id' => $batch, 'workflow_id' => $workflow],
            ),
            $this->makeData(
                action: 'mgr.two',
                context: ['batch_id' => $batch, 'workflow_id' => $workflow],
            ),
        ]);

        $this->assertCount(2, ActivityLog::query()->workflowId($workflow)->get());
        $this->assertCount(2, ActivityLog::query()->wherePromoted('batch_id', $batch)->get());
        $this->assertTrue(ActivityLog::hasAdapter('system'));
        ActivityLog::register('custom_ops', \JOOservices\LaravelLogging\Adapters\SystemLogAdapter::class);
        $this->assertTrue(ActivityLog::hasAdapter('custom_ops'));
        ActivityLog::replace('custom_ops', \JOOservices\LaravelLogging\Adapters\SystemLogAdapter::class);
    }

    public function test_related_to_falls_back_to_batch_id(): void
    {
        $this->requiresMongoDb();
        $this->clearActivityLogs();

        $batch = 'batch-' . Str::uuid()->toString();
        $first = ActivityLog::system()->action('batch.a')->batchId($batch)->save();
        ActivityLog::system()->action('batch.b')->batchId($batch)->save();
        ActivityLog::system()->action('batch.c')->batchId('other')->save();

        // Clear correlation so relatedTo uses batch_id path.
        $first->forceFill(['correlation_id' => null])->save();
        $fresh = $first->fresh();
        $this->assertInstanceOf(ActivityLogRecord::class, $fresh);

        $related = ActivityLog::query()->relatedTo($fresh)->get();
        $this->assertCount(2, $related);
    }

    public function test_related_to_falls_back_to_record_id(): void
    {
        $this->requiresMongoDb();
        $this->clearActivityLogs();

        $record = ActivityLog::system()->action('solo.only')->save();
        $record->forceFill([
            'correlation_id' => null,
            'batch_id' => null,
            'context' => [],
        ])->save();

        $fresh = $record->fresh();
        $this->assertInstanceOf(ActivityLogRecord::class, $fresh);

        $related = ActivityLog::query()->relatedTo($fresh)->get();
        $this->assertCount(1, $related);
        $this->assertTrue($related->first()?->is($fresh));
    }

    public function test_http_middleware_swallows_logging_failures(): void
    {
        config()->set('laravel-logging.http.enabled', true);
        config()->set('laravel-logging.http.ignore_paths', []);
        config()->set('laravel-logging.http.queue', false);

        // Force adapter resolution to fail after the response is produced.
        config()->set('laravel-logging.adapters.system', 'App\\DoesNotExist\\SystemAdapter');

        $middleware = new LogHttpRequest();
        $response = $middleware->handle(
            Request::create('/still-ok', 'GET'),
            fn(): Response => new Response('ok', 200),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_related_to_uses_correlation_id(): void
    {
        $this->requiresMongoDb();
        $this->clearActivityLogs();

        $first = ActivityLog::system()->action('related.a')->correlationId('jump-1')->save();
        ActivityLog::system()->action('related.b')->correlationId('jump-1')->save();
        ActivityLog::system()->action('related.c')->correlationId('other')->save();

        $related = ActivityLog::query()->relatedTo($first)->get();

        $this->assertCount(2, $related);
        $this->assertTrue($related->every(fn(ActivityLogRecord $record): bool => $record->correlation_id === 'jump-1'));
    }

    public function test_ttl_index_definition_appears_when_enabled(): void
    {
        config()->set('laravel-logging.ttl.enabled', true);
        config()->set('laravel-logging.ttl.expire_after_days', 30);

        $ttl = collect(IndexesCommand::expectedIndexes())->first(
            fn(array $index): bool => ($index['options']['name'] ?? null) === 'ttl_occurred_at',
        );

        $this->assertIsArray($ttl);
        $this->assertSame(30 * 86400, $ttl['options']['expireAfterSeconds']);

        $occurredAtIndexes = collect(IndexesCommand::expectedIndexes())->filter(
            fn(array $index): bool => ($index['keys'] ?? null) === ['occurred_at' => 1],
        );
        $this->assertCount(1, $occurredAtIndexes);
    }

    public function test_activity_log_options_fluent_helpers(): void
    {
        $options = \JOOservices\LaravelLogging\ActivityLogOptions::make()
            ->dontSubmitEmptyLogs()
            ->actionPrefix('entity');

        $this->assertFalse($options->submitEmptyLogs);
        $this->assertSame('entity', $options->actionPrefix);
    }

    public function test_http_middleware_skips_when_disabled(): void
    {
        config()->set('laravel-logging.http.enabled', false);

        $middleware = new LogHttpRequest();
        $response = $middleware->handle(Request::create('/demo', 'GET'), fn(): Response => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_http_middleware_ignores_health_paths(): void
    {
        config()->set('laravel-logging.http.enabled', true);

        $middleware = new LogHttpRequest();
        $response = $middleware->handle(Request::create('/up', 'GET'), fn(): Response => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_http_middleware_logs_request_when_enabled(): void
    {
        $this->requiresMongoDb();
        $this->clearActivityLogs();

        config()->set('laravel-logging.http.enabled', true);
        config()->set('laravel-logging.http.queue', false);
        config()->set('laravel-logging.http.ignore_paths', ['up']);

        $middleware = new LogHttpRequest();
        $response = $middleware->handle(
            Request::create('/api/demo', 'POST'),
            fn(): Response => new Response('created', 201),
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertCount(1, ActivityLog::query()->action('http.request')->get());
    }

    public function test_http_middleware_can_queue_log_writes(): void
    {
        $this->requiresMongoDb();
        Queue::fake();

        config()->set('laravel-logging.http.enabled', true);
        config()->set('laravel-logging.http.queue', true);
        config()->set('laravel-logging.http.ignore_paths', []);

        $middleware = new LogHttpRequest();
        $middleware->handle(
            Request::create('/queued', 'GET'),
            fn(): Response => new Response('ok', 200),
        );

        Queue::assertPushed(StoreActivityLogJob::class);
    }

    public function test_make_log_adapter_command_is_registered(): void
    {
        $this->assertContains('make:log-adapter', array_keys(\Illuminate\Support\Facades\Artisan::all()));
    }

    public function test_make_log_adapter_generates_class_file(): void
    {
        $path = $this->app->basePath('app/Logging/Adapters/OpsAdapter.php');
        @unlink($path);

        $this->artisan('make:log-adapter', ['name' => 'OpsAdapter', '--type' => 'ops', '--force' => true])
            ->assertSuccessful();

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('class OpsAdapter', $contents);
        $this->assertStringContainsString("protected string \$adapter = 'ops'", $contents);
        $this->assertStringContainsString("protected string \$type = 'ops'", $contents);
        @unlink($path);
    }

    public function test_doctor_reports_disabled_retention_as_warning(): void
    {
        $this->requiresMongoDb();
        config()->set('laravel-logging.retention.enabled', false);

        $this->artisan('activity-log:doctor', ['--json' => true])
            ->assertSuccessful();
    }

    public function test_doctor_reports_ttl_and_http_and_mapper_config(): void
    {
        $this->requiresMongoDb();
        config()->set('laravel-logging.ttl.enabled', true);
        config()->set('laravel-logging.ttl.expire_after_days', 14);
        config()->set('laravel-logging.http.enabled', true);
        config()->set('laravel-logging.domain_mappers', [
            \JOOservices\LaravelLogging\Tests\Stubs\FakeDomainMapper::class,
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('activity-log:doctor', ['--json' => true]);
        /** @var array{status: string, checks: list<array{name: string, status: string, message: string}>} $payload */
        $payload = json_decode(\Illuminate\Support\Facades\Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $names = array_column($payload['checks'], 'name');
        $this->assertContains('ttl', $names);
        $this->assertContains('http', $names);
        $this->assertTrue(
            collect($payload['checks'])->contains(
                fn(array $check): bool => str_starts_with($check['name'], 'domain_mapper'),
            ),
        );
    }

    public function test_doctor_fails_on_invalid_domain_mapper_class(): void
    {
        $this->requiresMongoDb();
        config()->set('laravel-logging.domain_mappers', ['App\\DoesNotExist\\Mapper']);

        $this->artisan('activity-log:doctor')
            ->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function makeData(
        string $action,
        ?string $correlationId = null,
        array $context = [],
    ): ActivityLogData {
        return new ActivityLogData(
            uuid: (string) Str::uuid(),
            type: 'system',
            adapter: 'system',
            level: 'info',
            action: $action,
            message: null,
            actorType: null,
            actorId: null,
            subjectType: null,
            subjectId: null,
            causerType: null,
            causerId: null,
            source: null,
            sourceType: null,
            requestId: null,
            correlationId: $correlationId,
            traceId: null,
            ipAddress: null,
            userAgent: null,
            tenantId: null,
            properties: [],
            context: $context,
            changes: [],
            occurredAt: CarbonImmutable::now(),
        );
    }
}
