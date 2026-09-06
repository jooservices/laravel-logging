<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use ArrayIterator;
use Illuminate\Contracts\Support\Arrayable;
use JOOservices\LaravelLogging\Adapters\AuditLogAdapter;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Services\DefaultLogSanitizer;
use JOOservices\LaravelLogging\Support\AuditableAttributes;
use JOOservices\LaravelLogging\Tests\Stubs\TestModel;
use JOOservices\LaravelLogging\Tests\TestCase;
use JsonSerializable;

/**
 * Coverage for edge paths that do not require a live MongoDB connection.
 */
final class CoverageBoostTest extends TestCase
{
    private function auditAdapter(): AuditLogAdapter
    {
        return new AuditLogAdapter(
            $this->app->make(LogStoreInterface::class),
            $this->app->make(LogSanitizerInterface::class),
            $this->app->make(ActivityLogPayloadLimiterInterface::class),
            $this->app->make(LogContextResolverInterface::class),
        );
    }

    public function test_audit_lifecycle_helpers_and_attribute_filter(): void
    {
        $model = new TestModel([
            'id' => 9,
            'name' => 'alpha',
            'password' => 'secret',
            'remember_token' => 'tok',
        ]);
        $model->syncOriginal();
        $model->setAttribute('name', 'beta');

        $created = $this->auditAdapter()->created($model)->toData();
        $this->assertSame('created', $created->action);
        $this->assertArrayHasKey('after', $created->changes);
        $this->assertIsArray($created->changes['after']);
        $this->assertArrayNotHasKey('password', $created->changes['after']);

        $updated = $this->auditAdapter()->updated($model)->toData();
        $this->assertSame('updated', $updated->action);
        $this->assertArrayHasKey('name', $updated->changes);

        $deleted = $this->auditAdapter()->deleted($model)->toData();
        $this->assertSame('deleted', $deleted->action);
        $this->assertArrayHasKey('before', $deleted->changes);

        $restored = $this->auditAdapter()->restored($model)->toData();
        $this->assertSame('restored', $restored->action);
        $this->assertArrayHasKey('after', $restored->changes);

        $arrayable = new class implements Arrayable {
            public function toArray(): array
            {
                return ['flag' => true];
            }
        };
        $json = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'scalar';
            }
        };

        $fromArrayable = $this->auditAdapter()->action('arrayable')->changes($arrayable)->toData();
        $this->assertSame(['flag' => true], $fromArrayable->changes);

        $fromJson = $this->auditAdapter()->action('json')->changes($json)->toData();
        $this->assertSame(['value' => 'scalar'], $fromJson->changes);

        $filtered = AuditableAttributes::filter(
            attributes: ['' => 'skip', 'name' => 'x', 'secret' => 'y', 'visible' => 'z'],
            only: ['visible', 'secret'],
            except: ['secret'],
            hidden: ['hidden_field'],
        );
        $this->assertSame(['visible' => 'z'], $filtered);

        $hiddenSkipped = AuditableAttributes::filter(
            attributes: ['name' => 'x', 'secret_note' => 'y'],
            only: null,
            except: [],
            hidden: ['secret_note'],
        );
        $this->assertSame(['name' => 'x'], $hiddenSkipped);
    }

    public function test_sanitizer_handles_objects_and_traversables(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: ['token'],
            replacement: '[redacted]',
            valuePatterns: ['/(?i)^Bearer\s+/'],
        );

        $jsonObject = new class implements JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['token' => 'secret', 'ok' => 1];
            }
        };
        $scalarJson = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'Bearer abc.def.ghi';
            }
        };

        $payload = $sanitizer->sanitize([
            'wrapped' => $jsonObject,
            'iterator' => new ArrayIterator(['token' => 'x', 'keep' => 'y']),
            'plain' => (object) ['token' => 'z', 'keep' => 'y'],
            'scalar' => $scalarJson,
        ]);

        $this->assertSame('[redacted]', $payload['wrapped']['token']);
        $this->assertSame(1, $payload['wrapped']['ok']);
        $this->assertSame('[redacted]', $payload['iterator']['token']);
        $this->assertSame('y', $payload['iterator']['keep']);
        $this->assertSame('[redacted]', $payload['plain']['token']);
        $this->assertSame('[redacted]', $payload['scalar']);
    }

    public function test_store_prepare_truncates_oversized_document_and_non_array_bags(): void
    {
        $this->app['config']->set('laravel-logging.limits.max_document_bytes', 80);
        $this->app['config']->set('laravel-logging.limits.max_string_length', 5000);
        $this->app->forgetInstance(ActivityLogPayloadLimiterInterface::class);
        $this->app->forgetInstance(LogStoreInterface::class);

        /** @var LogStoreInterface $store */
        $store = $this->app->make(LogStoreInterface::class);

        $prepared = $store->prepare(new ActivityLogData(
            uuid: '22222222-2222-2222-2222-222222222222',
            type: 'activity',
            adapter: 'activity',
            level: 'info',
            action: 'budget.overflow',
            message: str_repeat('m', 40),
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
            properties: ['blob' => str_repeat('p', 200)],
            context: ['blob' => str_repeat('c', 200)],
            changes: ['blob' => str_repeat('h', 200)],
            occurredAt: '2026-01-01T00:00:00+00:00',
        ));

        $this->assertArrayHasKey('__truncated', $prepared->properties);
        $this->assertArrayHasKey('__truncated', $prepared->context);
        $this->assertArrayHasKey('__truncated', $prepared->changes);
    }

    public function test_make_log_adapter_command_writes_stub(): void
    {
        $path = $this->app->basePath('app/Logging/Adapters/CoverageOpsAdapter.php');
        @unlink($path);

        $this->artisan('make:log-adapter', [
            'name' => 'CoverageOpsAdapter',
            '--type' => 'ops',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('CoverageOpsAdapter', $contents);
        @unlink($path);
    }
}
