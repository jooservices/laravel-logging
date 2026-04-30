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
}
