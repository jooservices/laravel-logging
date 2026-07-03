<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelLogging\Adapters\ActivityLogAdapter;
use JOOservices\LaravelLogging\Adapters\AuditLogAdapter;
use JOOservices\LaravelLogging\Adapters\DomainLogAdapter;
use JOOservices\LaravelLogging\Adapters\SecurityLogAdapter;
use JOOservices\LaravelLogging\Adapters\SystemLogAdapter;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DomainLogMapperRegistry;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Tests\Stubs\TestModel;
use JOOservices\LaravelLogging\Tests\TestCase;
use RuntimeException;

final class AdapterDataTest extends TestCase
{
    private LogStoreInterface $store;

    private LogSanitizerInterface $sanitizer;

    private ActivityLogPayloadLimiterInterface $payloadLimiter;

    private LogContextResolverInterface $contextResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresMongoDb();
        $this->clearActivityLogs();
        $this->clearCollection('audited_test_models');

        $this->store = $this->app->make(LogStoreInterface::class);
        $this->sanitizer = $this->app->make(LogSanitizerInterface::class);
        $this->payloadLimiter = $this->app->make(ActivityLogPayloadLimiterInterface::class);
        $this->contextResolver = $this->app->make(LogContextResolverInterface::class);
    }

    public function test_activity_defaults_and_common_fluent_data(): void
    {
        $actor = new TestModel(['id' => 123]);
        $subject = new TestModel(['id' => 456]);

        $record = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('provider.disabled')
            ->by($actor)
            ->on($subject)
            ->causedByExternal('api-client', 789)
            ->properties(['items' => 10, 'password' => 'secret'])
            ->context(['nested' => ['token' => 'abc']])
            ->save();

        $this->assertSame('activity', $record->type);
        $this->assertSame('activity', $record->adapter);
        $this->assertSame('info', $record->level);
        $this->assertSame(TestModel::class, $record->actor_type);
        $this->assertSame('123', $record->actor_id);
        $this->assertSame(TestModel::class, $record->subject_type);
        $this->assertSame('456', $record->subject_id);
        $this->assertSame('api-client', $record->causer_type);
        $this->assertSame('789', $record->causer_id);
        $this->assertSame('[redacted]', $record->properties['password']);
        $this->assertSame('[redacted]', $record->context['nested']['token']);
        $this->assertNotNull($record->occurred_at);
    }

    public function test_changes_are_sanitized_recursively(): void
    {
        $record = (new AuditLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('credentials.updated')
            ->changes([
                'password' => ['old' => 'before', 'new' => 'after'],
                'profile' => ['name' => 'Taylor', 'token' => 'abc'],
            ])
            ->save();

        $this->assertSame('[redacted]', $record->changes['password']);
        $this->assertSame('[redacted]', $record->changes['profile']['token']);
        $this->assertSame('Taylor', $record->changes['profile']['name']);
    }

    public function test_batch_and_workflow_helpers_store_string_context_ids(): void
    {
        $record = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('crawler.batch.completed')
            ->context(['provider' => 'onejav'])
            ->batchId(123)
            ->workflowId('crawl-456')
            ->save();

        $this->assertSame('onejav', $record->context['provider']);
        $this->assertSame('123', $record->context['batch_id']);
        $this->assertSame('crawl-456', $record->context['workflow_id']);
    }

    public function test_string_targets_are_not_parsed(): void
    {
        $record = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('checked')
            ->by('system')
            ->on('external:provider:123')
            ->causedBy('scheduler')
            ->save();

        $this->assertSame('system', $record->actor_type);
        $this->assertNull($record->actor_id);
        $this->assertSame('external:provider:123', $record->subject_type);
        $this->assertNull($record->subject_id);
        $this->assertSame('scheduler', $record->causer_type);
        $this->assertNull($record->causer_id);
    }

    public function test_external_methods_cast_ids_to_strings(): void
    {
        $record = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('external')
            ->byExternal('api-client', 'crawlerx')
            ->onExternal('provider', 123)
            ->causedByExternal('automation')
            ->save();

        $this->assertSame('crawlerx', $record->actor_id);
        $this->assertSame('123', $record->subject_id);
        $this->assertNull($record->causer_id);
    }

    public function test_audit_changes_from_filters_fields(): void
    {
        $record = (new AuditLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('config.updated')
            ->only(['enabled', 'count'])
            ->except(['count'])
            ->changesFrom(['enabled' => false, 'count' => 1, 'name' => 'a'], ['enabled' => true, 'count' => 2, 'name' => 'b'])
            ->save();

        $this->assertSame(['enabled' => ['old' => false, 'new' => true]], $record->changes);
    }

    public function test_audit_log_only_dirty_can_include_unchanged_fields(): void
    {
        $record = (new AuditLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('config.checked')
            ->logOnlyDirty(false)
            ->changesFrom(['enabled' => true], ['enabled' => true])
            ->save();

        $this->assertSame(['enabled' => ['old' => true, 'new' => true]], $record->changes);
    }

    public function test_security_login_failed_sets_action_level_and_properties(): void
    {
        $record = (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->loginFailed('user@example.test')
            ->save();

        $this->assertSame('security', $record->type);
        $this->assertSame('warning', $record->level);
        $this->assertSame('login.failed', $record->action);
        $this->assertSame('user@example.test', $record->properties['identifier']);
    }

    public function test_domain_from_event_sets_minimal_projection(): void
    {
        $event = new class {};

        $record = (new DomainLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->fromEvent($event)
            ->save();

        $this->assertSame('domain', $record->type);
        $this->assertStringStartsWith('domain.', $record->action);
        $this->assertSame($event::class, $record->properties['event']);
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

        $record = (new DomainLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver, $registry))
            ->fromEvent($event)
            ->save();

        $this->assertSame('domain.invoice.paid', $record->action);
        $this->assertSame('invoice', $record->subject_type);
        $this->assertSame('123', $record->subject_id);
        $this->assertTrue($record->properties['mapped']);
    }

    public function test_system_shortcuts_capture_exception_context(): void
    {
        $record = (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->jobFailed('ProcessJob', new RuntimeException('Nope'))
            ->save();

        $this->assertSame('system', $record->type);
        $this->assertSame('error', $record->level);
        $this->assertSame('job.failed', $record->action);
        $this->assertSame('ProcessJob', $record->context['job']);
        $this->assertSame(RuntimeException::class, $record->context['exception']['class']);
    }

    public function test_system_shortcuts_capture_lifecycle_actions(): void
    {
        $cases = [
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->commandStarted('sync:run')
                ->save(),
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->commandCompleted('sync:run')
                ->save(),
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->jobStarted('ImportJob')
                ->save(),
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->jobCompleted('ImportJob')
                ->save(),
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->schedulerStarted()
                ->save(),
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->schedulerCompleted()
                ->save(),
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->schedulerFailed(new RuntimeException('scheduler down'))
                ->save(),
            (new SystemLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->exception(new RuntimeException('captured'))
                ->save(),
        ];

        $this->assertSame(
            ['command.started', 'command.completed', 'job.started', 'job.completed', 'scheduler.started', 'scheduler.completed', 'scheduler.failed', 'exception.captured'],
            array_map(static fn ($record) => $record->action, $cases),
        );
    }

    public function test_security_shortcuts_set_expected_actions_and_levels(): void
    {
        $actor = new TestModel(['id' => 1]);
        $apiKey = new TestModel(['id' => 99]);

        $records = [
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->loginSucceeded($actor)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->logout($actor)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->passwordChanged($actor)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->twoFactorEnabled($actor)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->twoFactorDisabled($actor)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->apiKeyCreated($actor, $apiKey)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->apiKeyDeleted($actor, $apiKey)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->permissionChanged($actor, $apiKey)
                ->save(),
            (new SecurityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
                ->suspicious('rate limit')
                ->save(),
        ];

        $this->assertSame(
            ['login.succeeded', 'logout', 'password.changed', '2fa.enabled', '2fa.disabled', 'api_key.created', 'api_key.deleted', 'permission.changed', 'suspicious.request'],
            array_map(static fn ($record) => $record->action, $records),
        );
        $this->assertSame('rate limit', $records[8]->properties['reason']);
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

        $record = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('request.checked')
            ->withRequest($request)
            ->save();

        $this->assertSame('req-1', $record->request_id);
        $this->assertSame('corr-1', $record->correlation_id);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $record->trace_id);
        $this->assertSame('UnitTest', $record->user_agent);
        $this->assertArrayNotHasKey('password', $record->context['request']);
    }

    public function test_queue_dispatch_pushes_store_job_without_saving_immediately(): void
    {
        Queue::fake();

        (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('queued.activity')
            ->queue('logging')
            ->dispatch();

        Queue::assertPushed(StoreActivityLogJob::class, function (StoreActivityLogJob $job): bool {
            return $job->queue === 'logging'
                && $job->data->action === 'queued.activity';
        });

        $this->assertSame(0, ActivityLogRecord::query()->where('action', 'queued.activity')->count());
    }

    public function test_queued_job_handle_persists_to_mongodb(): void
    {
        Queue::fake();

        $job = null;

        (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('queued.persisted')
            ->queue('logging')
            ->dispatch();

        Queue::assertPushed(StoreActivityLogJob::class, function (StoreActivityLogJob $queuedJob) use (&$job): bool {
            $job = $queuedJob;

            return $queuedJob->data->action === 'queued.persisted';
        });

        $this->assertInstanceOf(StoreActivityLogJob::class, $job);

        $job->handle($this->store);

        $record = ActivityLogRecord::query()->where('action', 'queued.persisted')->first();

        $this->assertNotNull($record);
    }

    public function test_save_remains_synchronous_even_after_queue_target_is_set(): void
    {
        Queue::fake();

        $record = (new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver))
            ->action('sync.activity')
            ->queue('logging')
            ->save();

        Queue::assertNothingPushed();
        $this->assertSame('sync.activity', $record->action);
    }

    public function test_sync_dispatch_persists_immediately_without_queue_job(): void
    {
        Queue::fake();

        $adapter = new ActivityLogAdapter($this->store, $this->sanitizer, $this->payloadLimiter, $this->contextResolver);

        $adapter
            ->action('sync.dispatch')
            ->sync()
            ->dispatch();

        $this->assertInstanceOf(LogAdapterInterface::class, $adapter);
        Queue::assertNothingPushed();
        $record = ActivityLogRecord::query()->where('action', 'sync.dispatch')->first();

        $this->assertNotNull($record);
    }
}
