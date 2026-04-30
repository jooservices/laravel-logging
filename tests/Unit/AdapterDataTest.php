<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelLogging\ActivityLogOptions;
use JOOservices\LaravelLogging\Adapters\ActivityLogAdapter;
use JOOservices\LaravelLogging\Adapters\AuditLogAdapter;
use JOOservices\LaravelLogging\Adapters\DomainLogAdapter;
use JOOservices\LaravelLogging\Adapters\SecurityLogAdapter;
use JOOservices\LaravelLogging\Adapters\SystemLogAdapter;
use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DomainLogMapperRegistry;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use JOOservices\LaravelLogging\Tests\Stubs\AuditedTestModel;
use JOOservices\LaravelLogging\Tests\Stubs\TestModel;
use JOOservices\LaravelLogging\Tests\TestCase;
use RuntimeException;

final class AdapterDataTest extends TestCase
{
    private LogStoreInterface $store;

    private LogSanitizerInterface $sanitizer;

    private LogContextResolverInterface $contextResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->useFakeStore();
        $this->sanitizer = $this->app->make(LogSanitizerInterface::class);
        $this->contextResolver = $this->app->make(LogContextResolverInterface::class);
    }

    public function test_activity_defaults_and_common_fluent_data(): void
    {
        $actor = new TestModel(['id' => 123]);
        $subject = new TestModel(['id' => 456]);

        $data = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('provider.disabled')
            ->by($actor)
            ->on($subject)
            ->causedByExternal('api-client', 789)
            ->properties(['items' => 10, 'password' => 'secret'])
            ->context(['nested' => ['token' => 'abc']])
            ->toData();

        $this->assertSame('activity', $data->type);
        $this->assertSame('activity', $data->adapter);
        $this->assertSame('info', $data->level);
        $this->assertSame(TestModel::class, $data->actorType);
        $this->assertSame('123', $data->actorId);
        $this->assertSame(TestModel::class, $data->subjectType);
        $this->assertSame('456', $data->subjectId);
        $this->assertSame('api-client', $data->causerType);
        $this->assertSame('789', $data->causerId);
        $this->assertSame('[REDACTED]', $data->properties['password']);
        $this->assertSame('[REDACTED]', $data->context['nested']['token']);
        $this->assertNotNull($data->occurredAt);
    }

    public function test_changes_are_sanitized_recursively(): void
    {
        $data = (new AuditLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('credentials.updated')
            ->changes([
                'password' => ['old' => 'before', 'new' => 'after'],
                'profile' => ['name' => 'Taylor', 'token' => 'abc'],
            ])
            ->toData();

        $this->assertSame('[REDACTED]', $data->changes['password']);
        $this->assertSame('[REDACTED]', $data->changes['profile']['token']);
        $this->assertSame('Taylor', $data->changes['profile']['name']);
    }

    public function test_batch_and_workflow_helpers_store_string_context_ids(): void
    {
        $data = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('crawler.batch.completed')
            ->context(['provider' => 'onejav'])
            ->batchId(123)
            ->workflowId('crawl-456')
            ->toData();

        $this->assertSame('onejav', $data->context['provider']);
        $this->assertSame('123', $data->context['batch_id']);
        $this->assertSame('crawl-456', $data->context['workflow_id']);
    }

    public function test_string_targets_are_not_parsed(): void
    {
        $data = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('checked')
            ->by('system')
            ->on('external:provider:123')
            ->causedBy('scheduler')
            ->toData();

        $this->assertSame('system', $data->actorType);
        $this->assertNull($data->actorId);
        $this->assertSame('external:provider:123', $data->subjectType);
        $this->assertNull($data->subjectId);
        $this->assertSame('scheduler', $data->causerType);
        $this->assertNull($data->causerId);
    }

    public function test_external_methods_cast_ids_to_strings(): void
    {
        $data = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('external')
            ->byExternal('api-client', 'crawlerx')
            ->onExternal('provider', 123)
            ->causedByExternal('automation')
            ->toData();

        $this->assertSame('crawlerx', $data->actorId);
        $this->assertSame('123', $data->subjectId);
        $this->assertNull($data->causerId);
    }

    public function test_audit_changes_from_filters_fields(): void
    {
        $data = (new AuditLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('config.updated')
            ->only(['enabled', 'count'])
            ->except(['count'])
            ->changesFrom(['enabled' => false, 'count' => 1, 'name' => 'a'], ['enabled' => true, 'count' => 2, 'name' => 'b'])
            ->toData();

        $this->assertSame(['enabled' => ['old' => false, 'new' => true]], $data->changes);
    }

    public function test_audit_log_only_dirty_can_include_unchanged_fields(): void
    {
        $data = (new AuditLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('config.checked')
            ->logOnlyDirty(false)
            ->changesFrom(['enabled' => true], ['enabled' => true])
            ->toData();

        $this->assertSame(['enabled' => ['old' => true, 'new' => true]], $data->changes);
    }

    public function test_security_login_failed_sets_action_level_and_properties(): void
    {
        $data = (new SecurityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->loginFailed('user@example.test')
            ->toData();

        $this->assertSame('security', $data->type);
        $this->assertSame('warning', $data->level);
        $this->assertSame('login.failed', $data->action);
        $this->assertSame('user@example.test', $data->properties['identifier']);
    }

    public function test_domain_from_event_sets_minimal_projection(): void
    {
        $event = new class {};

        $data = (new DomainLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->fromEvent($event)
            ->toData();

        $this->assertSame('domain', $data->type);
        $this->assertStringStartsWith('domain.', $data->action);
        $this->assertSame($event::class, $data->properties['event']);
    }

    public function test_domain_mapper_registry_uses_matching_mapper(): void
    {
        $event = new class
        {
            public string $subject = 'invoice';
        };
        $registry = new DomainLogMapperRegistry($this->app);

        $registry->register(fn (): DomainLogMapperInterface => new class implements DomainLogMapperInterface
        {
            public function supports(object $event): bool
            {
                return property_exists($event, 'subject');
            }

            public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface
            {
                return $adapter
                    ->action('domain.invoice.paid')
                    ->onExternal($event->subject, 123)
                    ->properties(['mapped' => true])
                    ->occurredAt('2026-01-01 00:00:00');
            }
        });

        $data = (new DomainLogAdapter($this->store, $this->sanitizer, $this->contextResolver, $registry))
            ->fromEvent($event)
            ->toData();

        $this->assertSame('domain.invoice.paid', $data->action);
        $this->assertSame('invoice', $data->subjectType);
        $this->assertSame('123', $data->subjectId);
        $this->assertTrue($data->properties['mapped']);
    }

    public function test_system_shortcuts_capture_exception_context(): void
    {
        $data = (new SystemLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->jobFailed('ProcessJob', new RuntimeException('Nope'))
            ->toData();

        $this->assertSame('system', $data->type);
        $this->assertSame('error', $data->level);
        $this->assertSame('job.failed', $data->action);
        $this->assertSame('ProcessJob', $data->context['job']);
        $this->assertSame(RuntimeException::class, $data->context['exception']['class']);
    }

    public function test_with_request_fills_safe_request_context(): void
    {
        $request = Request::create('/demo?x=1', 'POST', ['password' => 'secret'], [], [], [
            'HTTP_X_REQUEST_ID' => 'req-1',
            'HTTP_X_CORRELATION_ID' => 'corr-1',
            'HTTP_TRACEPARENT' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00',
            'HTTP_USER_AGENT' => 'UnitTest',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $data = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('request.checked')
            ->withRequest($request)
            ->toData();

        $this->assertSame('req-1', $data->requestId);
        $this->assertSame('corr-1', $data->correlationId);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $data->traceId);
        $this->assertSame('UnitTest', $data->userAgent);
        $this->assertArrayNotHasKey('password', $data->context['request']);
    }

    public function test_queue_dispatch_pushes_store_job_without_saving_immediately(): void
    {
        Queue::fake();

        (new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('queued.activity')
            ->queue('logging')
            ->dispatch();

        Queue::assertPushed(StoreActivityLogJob::class, function (StoreActivityLogJob $job): bool {
            return $job->queue === 'logging'
                && $job->data->action === 'queued.activity';
        });

        $this->assertCount(0, $this->store->records);
    }

    public function test_save_remains_synchronous_even_after_queue_target_is_set(): void
    {
        Queue::fake();

        (new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver))
            ->action('sync.activity')
            ->queue('logging')
            ->save();

        Queue::assertNothingPushed();
        $this->assertCount(1, $this->store->records);
        $this->assertSame('sync.activity', $this->store->records[0]->action);
    }

    public function test_sync_dispatch_persists_immediately_without_queue_job(): void
    {
        Queue::fake();

        $adapter = new ActivityLogAdapter($this->store, $this->sanitizer, $this->contextResolver);

        $adapter
            ->action('sync.dispatch')
            ->sync()
            ->dispatch();

        $this->assertInstanceOf(LogAdapterInterface::class, $adapter);
        Queue::assertNothingPushed();
        $this->assertCount(1, $this->store->records);
        $this->assertSame('sync.dispatch', $this->store->records[0]->action);
    }

    public function test_logs_activity_trait_records_filtered_dirty_changes(): void
    {
        $model = new AuditedTestModel(['id' => 1, 'name' => 'Old', 'secret' => 'before']);
        $model->syncOriginal();
        $model->forceFill(['name' => 'New', 'secret' => 'after']);
        $model->options = ActivityLogOptions::make()
            ->logOnly(['name', 'secret'])
            ->except(['secret'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();

        $model->logForTest('updated');

        $this->assertCount(1, $this->store->records);
        $this->assertSame('model.updated', $this->store->records[0]->action);
        $this->assertSame(['name' => ['old' => 'Old', 'new' => 'New']], $this->store->records[0]->changes);
    }

    public function test_logs_activity_trait_skips_empty_logs_when_configured(): void
    {
        $model = new AuditedTestModel(['id' => 1, 'name' => 'Same']);
        $model->syncOriginal();
        $model->options = ActivityLogOptions::make()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();

        $model->logForTest('updated');

        $this->assertCount(0, $this->store->records);
    }
}
