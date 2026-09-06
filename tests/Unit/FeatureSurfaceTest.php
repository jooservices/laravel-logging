<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JOOservices\LaravelLogging\Console\Commands\InstallActivityLogIndexesCommand as IndexesCommand;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Http\Middleware\LogHttpRequest;
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
    }

    public function test_http_middleware_skips_when_disabled(): void
    {
        config()->set('laravel-logging.http.enabled', false);

        $middleware = new LogHttpRequest();
        $response = $middleware->handle(Request::create('/demo', 'GET'), fn(): Response => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_make_log_adapter_command_is_registered(): void
    {
        $this->assertContains('make:log-adapter', array_keys(\Illuminate\Support\Facades\Artisan::all()));
    }

    public function test_doctor_reports_disabled_retention_as_warning(): void
    {
        $this->requiresMongoDb();
        config()->set('laravel-logging.retention.enabled', false);

        $this->artisan('activity-log:doctor', ['--json' => true])
            ->assertSuccessful();
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
