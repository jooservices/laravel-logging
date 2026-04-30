<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelLogging\ActivityLogOptions;
use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Tests\Stubs\AuditedTestModel;
use JOOservices\LaravelLogging\Tests\TestCase;
use MongoDB\Model\IndexInfo;

final class OperationalFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresMongoDb();
        $this->clearActivityLogs();
        $this->clearCollection('audited_test_models');
    }

    public function test_queue_dispatch_job_handle_persists_real_record(): void
    {
        Queue::fake();
        $job = null;

        ActivityLog::activity()
            ->action('queued.real')
            ->queue('logging')
            ->dispatch();

        Queue::assertPushed(StoreActivityLogJob::class, function (StoreActivityLogJob $queuedJob) use (&$job): bool {
            $job = $queuedJob;

            return $queuedJob->queue === 'logging'
                && $queuedJob->data->action === 'queued.real';
        });

        $this->assertInstanceOf(StoreActivityLogJob::class, $job);
        $this->assertSame(0, ActivityLogRecord::query()->where('action', 'queued.real')->count());

        /** @var LogStoreInterface $store */
        $store = $this->app->make(LogStoreInterface::class);
        $job->handle($store);

        $record = ActivityLogRecord::query()->where('action', 'queued.real')->first();

        $this->assertNotNull($record);
        $this->assertSame('activity', $record->type);
    }

    public function test_model_audit_trait_persists_create_update_and_delete_logs(): void
    {
        $model = new AuditedTestModel(['name' => 'Old', 'secret' => 'before']);
        $model->options = ActivityLogOptions::make()
            ->logOnly(['name', 'secret'])
            ->except(['secret'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
        $model->save();

        $model->forceFill(['name' => 'New', 'secret' => 'after']);
        $model->save();
        $model->delete();

        $records = ActivityLogRecord::query()
            ->where('subject_type', AuditedTestModel::class)
            ->orderBy('action')
            ->get();

        $this->assertCount(3, $records);
        $this->assertSame(['model.created', 'model.deleted', 'model.updated'], $records->pluck('action')->all());
        $this->assertSame(['name' => ['old' => null, 'new' => 'Old']], $records->firstWhere('action', 'model.created')?->changes);
        $this->assertSame(['name' => ['old' => 'Old', 'new' => 'New']], $records->firstWhere('action', 'model.updated')?->changes);
        $this->assertSame(['name' => ['old' => 'New', 'new' => null]], $records->firstWhere('action', 'model.deleted')?->changes);
    }

    public function test_domain_mapper_registry_persists_mapped_event_through_facade(): void
    {
        $event = new class
        {
            public string $aggregate = 'invoice';
        };

        /** @var DomainLogMapperRegistryInterface $registry */
        $registry = $this->app->make(DomainLogMapperRegistryInterface::class);
        $registry->register(fn (): DomainLogMapperInterface => new class implements DomainLogMapperInterface
        {
            public function supports(object $event): bool
            {
                return property_exists($event, 'aggregate');
            }

            public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface
            {
                return $adapter
                    ->action('domain.invoice.paid')
                    ->onExternal($event->aggregate, 123)
                    ->properties(['mapped' => true]);
            }
        });

        $record = ActivityLog::domain()
            ->fromEvent($event)
            ->save();

        $this->assertSame('domain.invoice.paid', $record->action);
        $this->assertSame('invoice', $record->subject_type);
        $this->assertSame('123', $record->subject_id);
        $this->assertTrue($record->properties['mapped']);
    }

    public function test_index_command_creates_expected_indexes(): void
    {
        $this->artisan('activity-log:indexes')->assertSuccessful();

        $indexes = iterator_to_array(DB::connection('mongodb')->getCollection('activity_logs')->listIndexes());
        $keys = array_map(
            static fn (IndexInfo $index): array => $index->getKey(),
            $indexes,
        );

        $this->assertContains(['uuid' => 1], $keys);
        $this->assertContains(['type' => 1, 'action' => 1, 'occurred_at' => -1], $keys);
    }
}
