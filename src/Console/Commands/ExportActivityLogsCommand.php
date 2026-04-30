<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\LaravelLogging\ActivityLogManager;
use JOOservices\LaravelLogging\ActivityLogQuery;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

final class ExportActivityLogsCommand extends Command
{
    protected $signature = 'activity-log:export
        {--type= : Filter by type}
        {--action= : Filter by action}
        {--from= : Include logs from this occurred_at date}
        {--to= : Include logs until this occurred_at date}
        {--format=jsonl : Export format: jsonl or csv}
        {--output= : Output file path. Writes to stdout when omitted}';

    protected $description = 'Export activity logs as JSONL or CSV.';

    public function handle(ActivityLogManager $manager): int
    {
        $format = (string) $this->option('format');

        if (! in_array($format, ['jsonl', 'csv'], true)) {
            $this->error('Invalid format. Supported formats: jsonl, csv.');

            return self::FAILURE;
        }

        $query = $this->filteredQuery($manager);
        $output = $this->option('output');
        $handle = $output ? fopen((string) $output, 'wb') : STDOUT;

        if (! is_resource($handle)) {
            $this->error('Unable to open export output path.');

            return self::FAILURE;
        }

        $this->writeExport($query, $format, $handle);

        if ($output) {
            fclose($handle);
            $this->info("Activity logs exported to {$output}.");
        }

        return self::SUCCESS;
    }

    private function filteredQuery(ActivityLogManager $manager): ActivityLogQuery
    {
        $query = $manager->query();

        if ($this->option('type')) {
            $query->type((string) $this->option('type'));
        }

        if ($this->option('action')) {
            $query->action((string) $this->option('action'));
        }

        if ($this->option('from')) {
            $query->since((string) $this->option('from'));
        }

        if ($this->option('to')) {
            $query->until((string) $this->option('to'));
        }

        return $query;
    }

    /**
     * @param  resource  $handle
     */
    private function writeExport(ActivityLogQuery $query, string $format, mixed $handle): void
    {
        if ($format === 'csv') {
            fputcsv($handle, ['uuid', 'type', 'adapter', 'level', 'action', 'actor_type', 'actor_id', 'subject_type', 'subject_id', 'occurred_at', 'created_at']);
        }

        $query->latest()->each(function (ActivityLogRecord $record) use ($format, $handle): void {
            $format === 'jsonl'
                ? $this->writeJsonLine($handle, $record)
                : $this->writeCsvRow($handle, $record);
        });
    }

    /**
     * @param  resource  $handle
     */
    private function writeJsonLine(mixed $handle, ActivityLogRecord $record): void
    {
        fwrite($handle, json_encode($record->toArray(), JSON_THROW_ON_ERROR).PHP_EOL);
    }

    /**
     * @param  resource  $handle
     */
    private function writeCsvRow(mixed $handle, ActivityLogRecord $record): void
    {
        fputcsv($handle, [
            $record->uuid,
            $record->type,
            $record->adapter,
            $record->level,
            $record->action,
            $record->actor_type,
            $record->actor_id,
            $record->subject_type,
            $record->subject_id,
            (string) $record->occurred_at,
            (string) $record->created_at,
        ]);
    }
}
