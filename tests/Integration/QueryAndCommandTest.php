<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
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

        $this->artisan('activity-log:prune', ['--type' => 'activity', '--days' => 90, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, DB::connection('mongodb')->getCollection('activity_logs')->countDocuments());
        $this->assertNotNull(ActivityLog::query()->action('recent')->first());
    }

    public function test_prune_defaults_to_dry_run_without_force(): void
    {
        ActivityLog::activity()
            ->action('old.default')
            ->occurredAt(CarbonImmutable::now('UTC')->subDays(120))
            ->save();

        $this->artisan('activity-log:prune', ['--type' => 'activity', '--days' => 90])
            ->expectsOutputToContain('Mode: dry-run')
            ->assertSuccessful();

        $this->assertSame(1, DB::connection('mongodb')->getCollection('activity_logs')->countDocuments());
    }

    public function test_prune_type_filter_and_before_cutoff(): void
    {
        ActivityLog::system()
            ->action('system.old')
            ->occurredAt(CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'))
            ->save();

        ActivityLog::audit()
            ->action('audit.old')
            ->occurredAt(CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'))
            ->save();

        $this->artisan('activity-log:prune', ['--type' => 'system', '--before' => '2026-02-01', '--force' => true, '--json' => true])
            ->expectsOutputToContain('"deleted": 1')
            ->assertSuccessful();

        $this->assertNull(ActivityLog::query()->action('system.old')->first());
        $this->assertNotNull(ActivityLog::query()->action('audit.old')->first());
    }

    public function test_prune_rejects_conflicting_and_invalid_options(): void
    {
        $this->artisan('activity-log:prune', ['--days' => 30, '--before' => '2026-01-01'])
            ->assertExitCode(2);

        $this->artisan('activity-log:prune', ['--type' => 'unknown', '--dry-run' => true])
            ->assertExitCode(2);

        $this->artisan('activity-log:prune', ['--before' => '2999-01-01', '--force' => true])
            ->assertExitCode(2);
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
        $this->assertStringStartsWith('uuid,type,action,description,level', (string) file($csv)[0]);

        @unlink($jsonl);
        @unlink($csv);
    }

    public function test_export_refuses_overwrite_without_force_and_allows_force(): void
    {
        ActivityLog::audit()->action('export.force')->save();

        $path = sys_get_temp_dir().'/activity-log-export-force.jsonl';
        file_put_contents($path, 'existing');

        $this->artisan('activity-log:export', ['--output' => $path])
            ->assertExitCode(2);

        $this->artisan('activity-log:export', ['--output' => $path, '--force' => true, '--json' => true])
            ->expectsOutputToContain('"exported": 1')
            ->assertSuccessful();

        @unlink($path);
    }

    public function test_export_rejects_invalid_format_and_output_path(): void
    {
        $this->artisan('activity-log:export', ['--format' => 'xml'])
            ->assertExitCode(2);

        $this->artisan('activity-log:export', ['--output' => sys_get_temp_dir().'/missing-directory/activity.jsonl'])
            ->assertExitCode(2);
    }

    public function test_sensitive_and_oversized_values_are_not_stored_raw(): void
    {
        $this->app['config']->set('laravel-logging.limits.max_string_length', 5);
        $this->app->forgetInstance(ActivityLogPayloadLimiterInterface::class);

        ActivityLog::activity()
            ->action('payload.guarded')
            ->properties(['Password' => 'secret', 'description' => 'abcdefghij'])
            ->save();

        $record = ActivityLog::query()->action('payload.guarded')->first();

        $this->assertNotNull($record);
        $this->assertSame('[redacted]', $record->properties['Password']);
        $this->assertSame('abcde[truncated]', $record->properties['description']);
    }
}
