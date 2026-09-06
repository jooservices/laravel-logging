<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Tests\TestCase;

final class MongoLogStoreTest extends TestCase
{
    public function test_prepare_truncates_oversized_document_bags(): void
    {
        $this->app['config']->set('laravel-logging.limits.max_document_bytes', 80);
        $this->app['config']->set('laravel-logging.limits.max_string_length', 5000);
        $this->app->forgetInstance(ActivityLogPayloadLimiterInterface::class);
        $this->app->forgetInstance(LogStoreInterface::class);

        /** @var LogStoreInterface $store */
        $store = $this->app->make(LogStoreInterface::class);

        $prepared = $store->prepare($this->sampleData(
            action: 'budget.overflow',
            message: str_repeat('m', 40),
            properties: ['blob' => str_repeat('p', 200)],
            context: ['blob' => str_repeat('c', 200)],
            changes: ['blob' => str_repeat('h', 200)],
        ));

        $this->assertArrayHasKey('__truncated', $prepared->properties);
        $this->assertArrayHasKey('__truncated', $prepared->context);
        $this->assertArrayHasKey('__truncated', $prepared->changes);
    }

    public function test_prepare_clamps_identity_scalars_under_field_limits(): void
    {
        $this->app['config']->set('laravel-logging.limits.max_document_bytes', 220);
        $this->app['config']->set('laravel-logging.limits.max_string_length', 5000);
        $this->app->forgetInstance(ActivityLogPayloadLimiterInterface::class);
        $this->app->forgetInstance(LogStoreInterface::class);

        /** @var LogStoreInterface $store */
        $store = $this->app->make(LogStoreInterface::class);

        $prepared = $store->prepare($this->sampleData(
            action: 'budget.clamp-ids',
            message: 'ok',
            requestId: str_repeat('r', 200),
            correlationId: str_repeat('c', 200),
            traceId: str_repeat('t', 100),
            ipAddress: str_repeat('1', 80),
            userAgent: str_repeat('u', 600),
            properties: ['blob' => str_repeat('p', 400)],
            context: ['blob' => str_repeat('x', 400)],
            changes: ['blob' => str_repeat('y', 400)],
        ));

        $this->assertSame(128, mb_strlen((string) $prepared->requestId));
        $this->assertSame(128, mb_strlen((string) $prepared->correlationId));
        $this->assertSame(64, mb_strlen((string) $prepared->traceId));
        $this->assertSame(64, mb_strlen((string) $prepared->ipAddress));
        $this->assertSame(512, mb_strlen((string) $prepared->userAgent));
        $this->assertArrayHasKey('__truncated', $prepared->properties);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $changes
     */
    private function sampleData(
        string $action,
        ?string $message = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?string $traceId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $properties = [],
        array $context = [],
        array $changes = [],
    ): ActivityLogData {
        return new ActivityLogData(
            uuid: '22222222-2222-2222-2222-222222222222',
            type: 'activity',
            adapter: 'activity',
            level: 'info',
            action: $action,
            message: $message,
            actorType: null,
            actorId: null,
            subjectType: null,
            subjectId: null,
            causerType: null,
            causerId: null,
            source: null,
            sourceType: null,
            requestId: $requestId,
            correlationId: $correlationId,
            traceId: $traceId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            tenantId: null,
            properties: $properties,
            context: $context,
            changes: $changes,
            occurredAt: '2026-01-01T00:00:00+00:00',
        );
    }
}
