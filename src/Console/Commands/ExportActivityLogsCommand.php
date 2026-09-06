<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use Throwable;

final class ExportActivityLogsCommand extends Command
{
    protected $signature = 'activity-log:export
        {--type= : Filter by type}
        {--action= : Filter by action}
        {--from= : Include logs from this occurred_at date}
        {--to= : Exclude logs at or after this occurred_at date}
        {--format=jsonl : Export format: jsonl or csv}
        {--output= : Output file path. Writes to stdout when omitted}
        {--chunk= : Stream chunk size}
        {--force : Overwrite an existing output file}
        {--json : Output machine-readable summary when exporting to a file}';

    protected $description = 'Export activity logs as JSONL or CSV.';

    public function handle(ActivityLogRepository $repository): int
    {
        $inputError = $this->validateOptions();

        if ($inputError !== null) {
            $this->error($inputError);

            return 2;
        }

        $format = (string) $this->option('format');
        $output = $this->filledOption('output') ? (string) $this->option('output') : null;
        $handle = $output === null ? STDOUT : fopen($output, 'wb');

        if (! is_resource($handle)) {
            $this->error('Unable to open export output path.');

            return self::FAILURE;
        }

        $count = $this->writeExport($this->filteredQuery($repository), $format, $handle);

        if ($output !== null) {
            fclose($handle);
            $summary = ['format' => $format, 'output' => $output, 'exported' => $count];

            $this->option('json')
                ? $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))
                : $this->info("Exported {$count} activity logs to {$output}.");
        }

        return self::SUCCESS;
    }

    private function validateOptions(): ?string
    {
        foreach ([$this->validateFormat(), $this->validateSummaryOutput(), $this->validateChunk(), $this->validateDates(), $this->validateOutputPath()] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function validateFormat(): ?string
    {
        $formats = config('laravel-logging.export.formats', ['jsonl', 'csv']);

        return is_array($formats) && in_array((string) $this->option('format'), $formats, true)
            ? null
            : 'Invalid format. Supported formats: jsonl, csv.';
    }

    private function validateSummaryOutput(): ?string
    {
        return $this->option('json') && ! $this->filledOption('output')
            ? 'Option --json requires --output to avoid mixing summary JSON with exported data on stdout.'
            : null;
    }

    private function validateChunk(): ?string
    {
        return $this->filledOption('chunk') && filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            ? 'Option --chunk must be a positive integer.'
            : null;
    }

    private function validateDates(): ?string
    {
        foreach (['from', 'to'] as $option) {
            if (! $this->filledOption($option)) {
                continue;
            }

            try {
                CarbonImmutable::parse((string) $this->option($option));
            } catch (Throwable) {
                return "Option --{$option} must be a valid date or datetime.";
            }
        }

        return null;
    }

    private function validateOutputPath(): ?string
    {
        if (! $this->filledOption('output')) {
            return null;
        }

        $path = (string) $this->option('output');
        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            return 'Output directory does not exist or is not writable.';
        }

        if (file_exists($path) && ! $this->option('force')) {
            return 'Output file already exists. Use --force to overwrite it.';
        }

        return null;
    }

    /**
     * @return Builder<ActivityLogRecord>
     */
    private function filteredQuery(ActivityLogRepository $repository): Builder
    {
        /** @var Builder<ActivityLogRecord> $query */
        $query = $repository->newQuery();

        if ($this->filledOption('type')) {
            $query->where('type', (string) $this->option('type'));
        }

        if ($this->filledOption('action')) {
            $query->where('action', (string) $this->option('action'));
        }

        if ($this->filledOption('from')) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse((string) $this->option('from')));
        }

        if ($this->filledOption('to')) {
            $query->where('occurred_at', '<', CarbonImmutable::parse((string) $this->option('to')));
        }

        return $query->oldest('occurred_at');
    }

    /**
     * @param  Builder<ActivityLogRecord>  $query
     * @param  resource  $handle
     */
    private function writeExport(Builder $query, string $format, mixed $handle): int
    {
        if ($format === 'csv') {
            fputcsv($handle, ['uuid', 'type', 'action', 'description', 'level', 'actor_type', 'actor_id', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'request_id', 'correlation_id', 'trace_id', 'occurred_at', 'created_at']);
        }

        $count = 0;

        $query->chunk($this->chunkSize(), function ($records) use ($format, $handle, &$count): void {
            foreach ($records as $record) {
                $format === 'jsonl'
                    ? $this->writeJsonLine($handle, $record)
                    : $this->writeCsvRow($handle, $record);

                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  resource  $handle
     */
    private function writeJsonLine(mixed $handle, ActivityLogRecord $record): void
    {
        fwrite($handle, json_encode($record->toArray(), JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    /**
     * @param  resource  $handle
     */
    private function writeCsvRow(mixed $handle, ActivityLogRecord $record): void
    {
        fputcsv($handle, [
            $record->uuid,
            $record->type,
            $record->action,
            $record->message,
            $record->level,
            $record->actor_type,
            $record->actor_id,
            $record->subject_type,
            $record->subject_id,
            $record->causer_type,
            $record->causer_id,
            $record->request_id,
            $record->correlation_id,
            $record->trace_id,
            (string) $record->occurred_at,
            (string) $record->created_at,
        ]);
    }

    private function chunkSize(): int
    {
        return $this->filledOption('chunk')
            ? (int) $this->option('chunk')
            : (int) config('laravel-logging.export.chunk_size', 500);
    }

    private function filledOption(string $option): bool
    {
        $value = $this->option($option);

        return $value !== null && $value !== '';
    }
}
