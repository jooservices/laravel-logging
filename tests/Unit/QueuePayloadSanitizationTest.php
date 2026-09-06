<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelLogging\Adapters\ActivityLogAdapter;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use JOOservices\LaravelLogging\Tests\TestCase;

/**
 * Queue serialization must not wait for the worker to redact bags.
 * Does not require MongoDB — prepare() and Queue::fake() are enough.
 */
final class QueuePayloadSanitizationTest extends TestCase
{
    public function test_store_prepare_redacts_sensitive_bags(): void
    {
        /** @var LogStoreInterface $store */
        $store = $this->app->make(LogStoreInterface::class);

        $prepared = $store->prepare(new ActivityLogData(
            uuid: '11111111-1111-1111-1111-111111111111',
            type: 'activity',
            adapter: 'activity',
            level: 'info',
            action: 'queue.prepare',
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
            correlationId: null,
            traceId: null,
            ipAddress: null,
            userAgent: null,
            tenantId: null,
            properties: ['password' => 'secret', 'count' => 1],
            context: ['token' => 'abc'],
            changes: ['api_key' => 'xyz'],
            occurredAt: null,
        ));

        $this->assertSame('[redacted]', $prepared->properties['password']);
        $this->assertSame(1, $prepared->properties['count']);
        $this->assertSame('[redacted]', $prepared->context['token']);
        $this->assertSame('[redacted]', $prepared->changes['api_key']);
    }

    public function test_async_dispatch_enqueues_already_redacted_dto(): void
    {
        Queue::fake();

        /** @var LogStoreInterface $store */
        $store = $this->app->make(LogStoreInterface::class);

        (new ActivityLogAdapter(
            $store,
            $this->app->make(LogSanitizerInterface::class),
            $this->app->make(ActivityLogPayloadLimiterInterface::class),
            $this->app->make(LogContextResolverInterface::class),
        ))
            ->action('queued.redacted')
            ->properties(['password' => 'secret', 'count' => 2])
            ->context(['token' => 'abc', 'ok' => true])
            ->queue('logging')
            ->dispatch();

        Queue::assertPushed(StoreActivityLogJob::class, function (StoreActivityLogJob $job): bool {
            return $job->queue === 'logging'
                && $job->data->action === 'queued.redacted'
                && ($job->data->properties['password'] ?? null) === '[redacted]'
                && ($job->data->properties['count'] ?? null) === 2
                && ($job->data->context['token'] ?? null) === '[redacted]'
                && ($job->data->context['ok'] ?? null) === true;
        });
    }
}
