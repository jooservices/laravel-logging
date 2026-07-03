<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Integration;

use Illuminate\Support\Facades\DB;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Tests\TestCase;

final class PersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresMongoDb();
        $this->clearActivityLogs();
    }

    public function test_facade_persists_document_through_mongo_store(): void
    {
        $record = ActivityLog::activity()
            ->action('crawler.completed')
            ->bySystem()
            ->onExternal('provider', 123)
            ->properties(['items' => 120])
            ->save();

        $this->assertInstanceOf(ActivityLogRecord::class, $record);
        $this->assertSame('activity_logs', $record->getTable());
        $this->assertSame('123', $record->subject_id);

        $document = DB::connection('mongodb')->getCollection('activity_logs')->findOne(['uuid' => $record->uuid]);

        $this->assertNotNull($document);
        $this->assertSame('crawler.completed', $document['action']);
        $this->assertSame('system', $document['actor_type']);
        $this->assertNull($document['actor_id']);
    }

    public function test_promoted_fields_are_copied_to_top_level_on_persist(): void
    {
        $this->app['config']->set('laravel-logging.promoted_fields', [
            'site_id' => 'properties.site_id',
            'workflow_id' => 'context.workflow_id',
        ]);

        ActivityLog::activity()
            ->action('crawl.started')
            ->properties(['site_id' => 42])
            ->context(['workflow_id' => 'wf-1'])
            ->save();

        $record = ActivityLog::query()->action('crawl.started')->first();

        $this->assertNotNull($record);
        $this->assertSame(42, $record->site_id);
        $this->assertSame('wf-1', $record->workflow_id);
        $this->assertSame(42, $record->properties['site_id']);
    }

    public function test_tenant_id_is_persisted_and_queryable(): void
    {
        ActivityLog::activity()
            ->action('tenant.checked')
            ->tenantId('tenant-99')
            ->save();

        ActivityLog::activity()
            ->action('tenant.other')
            ->tenantId('tenant-1')
            ->save();

        $record = ActivityLog::query()->tenantId('tenant-99')->action('tenant.checked')->first();

        $this->assertNotNull($record);
        $this->assertSame('tenant-99', $record->tenant_id);
    }
}
