<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Tests\Stubs\TestModel;
use JOOservices\LaravelLogging\Tests\TestCase;

final class QueryAndCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresMongoDb();
        $this->clearActivityLogs();
    }

    public function test_query_api_filters_and_orders_records(): void
    {
        $subject = new TestModel(['id' => 123]);
        $actor = new TestModel(['id' => 456]);

        ActivityLog::audit()
            ->action('config.updated')
            ->by($actor)
            ->on($subject)
            ->correlationId('corr-1')
            ->requestId('req-1')
            ->traceId('trace-1')
            ->occurredAt(CarbonImmutable::parse('2026-01-01 00:00:00'))
            ->save();

        ActivityLog::audit()
            ->action('config.updated')
            ->by($actor)
            ->on($subject)
            ->correlationId('corr-1')
            ->occurredAt(CarbonImmutable::parse('2026-01-02 00:00:00'))
            ->save();

        $records = ActivityLog::query()
            ->type('audit')
            ->adapter('audit')
            ->level('info')
            ->action('config.updated')
            ->forSubject($subject)
            ->byActor($actor)
            ->correlationId('corr-1')
            ->between(CarbonImmutable::parse('2026-01-01 00:00:00'), CarbonImmutable::parse('2026-01-03 00:00:00'))
            ->latest()
            ->get();

        $this->assertCount(2, $records);
        $this->assertSame('123', $records->first()?->subject_id);
        $this->assertSame('456', $records->first()?->actor_id);
        $this->assertNotNull(ActivityLog::records()->requestId('req-1')->first());
        $this->assertSame(1, ActivityLog::query()->traceId('trace-1')->paginate(15)->total());
    }

    public function test_query_string_identity_does_not_parse_and_explicit_id_is_string(): void
    {
        ActivityLog::activity()
            ->action('external.checked')
            ->byExternal('external:actor:1', 99)
            ->on('external:subject:2')
            ->causedBy('scheduler')
            ->save();

        $record = ActivityLog::query()
            ->byActor('external:actor:1', 99)
            ->forSubject('external:subject:2')
            ->causedBy('scheduler')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('99', $record->actor_id);
        $this->assertNull($record->subject_id);
        $this->assertNull($record->causer_id);
    }

    public function test_prune_dry_run_and_days_override(): void
    {
        ActivityLog::activity()
            ->action('old')
            ->occurredAt(CarbonImmutable::now()->subDays(120))
            ->save();

        ActivityLog::activity()
            ->action('recent')
            ->occurredAt(CarbonImmutable::now()->subDays(10))
            ->save();

        $this->artisan('activity-log:prune', ['--type' => 'activity', '--days' => 90, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(2, DB::connection('mongodb')->getCollection('activity_logs')->countDocuments());

        $this->artisan('activity-log:prune', ['--type' => 'activity', '--days' => 90])
            ->assertSuccessful();

        $this->assertSame(1, DB::connection('mongodb')->getCollection('activity_logs')->countDocuments());
        $this->assertNotNull(ActivityLog::query()->action('recent')->first());
    }

    public function test_prune_requires_force_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('activity-log:prune', ['--type' => 'activity', '--days' => 90])
            ->assertFailed();
    }

    public function test_export_jsonl_and_csv(): void
    {
        ActivityLog::audit()
            ->action('config.updated')
            ->occurredAt(CarbonImmutable::parse('2026-01-01 00:00:00'))
            ->save();

        $jsonl = sys_get_temp_dir().'/activity-log-export-test.jsonl';
        $csv = sys_get_temp_dir().'/activity-log-export-test.csv';

        @unlink($jsonl);
        @unlink($csv);

        $this->artisan('activity-log:export', [
            '--type' => 'audit',
            '--from' => '2025-12-31',
            '--format' => 'jsonl',
            '--output' => $jsonl,
        ])->assertSuccessful();

        $this->assertFileExists($jsonl);
        $this->assertSame('config.updated', json_decode((string) file($jsonl)[0], true)['action']);

        $this->artisan('activity-log:export', [
            '--type' => 'audit',
            '--format' => 'csv',
            '--output' => $csv,
        ])->assertSuccessful();

        $this->assertFileExists($csv);
        $this->assertStringStartsWith('uuid,type,adapter,level,action', (string) file($csv)[0]);

        @unlink($jsonl);
        @unlink($csv);
    }
}
