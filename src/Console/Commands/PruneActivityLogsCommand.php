<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use Throwable;

final class PruneActivityLogsCommand extends Command
{
    protected $signature = 'activity-log:prune
        {--type= : Prune only one log type}
        {--days= : Override retention days}
        {--before= : Explicit occurred_at cutoff date or datetime}
        {--chunk= : Delete chunk size}
        {--dry-run : Count matching logs without deleting}
        {--force : Actually delete matching logs}
        {--json : Output machine-readable JSON}';

    protected $description = 'Prune activity logs by occurred_at retention rules.';

    public function handle(ActivityLogRepository $repository): int
    {
        $inputError = $this->validateOptions();

        if ($inputError !== null) {
            $this->renderError($inputError);

            return 2;
        }

        $plan = $this->plan();

        if ($plan === null) {
            $this->renderResult('dry-run', null, null, 0, 0);

            return self::SUCCESS;
        }

        [$type, $cutoff] = $plan;

        if ($cutoff->isFuture()) {
            $this->renderError('Refusing to prune with a future cutoff.');

            return 2;
        }

        $query = $repository->newQuery()->where('occurred_at', '<', $cutoff);

        if ($type !== null) {
            $query->where('type', $type);
        }

        $matched = (clone $query)->toBase()->count();
        $mode = $this->option('force') ? 'force' : 'dry-run';
        $deleted = 0;

        if ($mode === 'force') {
            $deleted = $this->deleteMatchingRecords($query);
        }

        $this->renderResult($mode, $type, $cutoff, $matched, $deleted);

        return self::SUCCESS;
    }

    private function validateOptions(): ?string
    {
        foreach ([$this->validateCutoffOptions(), $this->validateIntegerOption('days'), $this->validateIntegerOption('chunk'), $this->validateType(), $this->validateBeforeDate()] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function validateCutoffOptions(): ?string
    {
        return $this->filledOption('days') && $this->filledOption('before')
            ? 'Options --days and --before cannot be used together.'
            : null;
    }

    private function validateIntegerOption(string $option): ?string
    {
        return $this->filledOption($option) && filter_var($this->option($option), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            ? "Option --{$option} must be a positive integer."
            : null;
    }

    private function validateType(): ?string
    {
        return $this->filledOption('type') && ! in_array((string) $this->option('type'), $this->knownTypes(), true)
            ? 'Unknown activity log type ['.$this->option('type').'].'
            : null;
    }

    private function validateBeforeDate(): ?string
    {
        if (! $this->filledOption('before')) {
            return null;
        }

        try {
            CarbonImmutable::parse((string) $this->option('before'));
        } catch (Throwable) {
            return 'Option --before must be a valid date or datetime.';
        }

        return null;
    }

    /**
     * @return array{0: string|null, 1: CarbonImmutable}|null
     */
    private function plan(): ?array
    {
        if ((bool) config('laravel-logging.retention.enabled', true) === false && ! $this->filledOption('days') && ! $this->filledOption('before')) {
            return null;
        }

        $type = $this->filledOption('type') ? (string) $this->option('type') : null;

        if ($this->filledOption('before')) {
            return [$type, CarbonImmutable::parse((string) $this->option('before'))];
        }

        $days = $this->filledOption('days') ? (int) $this->option('days') : $this->retentionDays($type);

        if ($days < 1) {
            return null;
        }

        return [$type, CarbonImmutable::now('UTC')->subDays($days)];
    }

    private function retentionDays(?string $type): int
    {
        if ($type !== null) {
            return (int) config("laravel-logging.retention.types.{$type}", config("laravel-logging.retention.defaults.{$type}", config('laravel-logging.retention.default_days', 180)));
        }

        return (int) config('laravel-logging.retention.default_days', 180);
    }

    /**
     * @return list<string>
     */
    private function knownTypes(): array
    {
        return array_values(array_unique(array_merge(
            array_keys((array) config('laravel-logging.adapters', [])),
            array_keys((array) config('laravel-logging.retention.types', config('laravel-logging.retention.defaults', []))),
        )));
    }

    private function chunkSize(): int
    {
        return $this->filledOption('chunk')
            ? (int) $this->option('chunk')
            : (int) config('laravel-logging.retention.chunk_size', 500);
    }

    private function deleteMatchingRecords(mixed $query): int
    {
        $deleted = 0;
        $batch = [];

        foreach ($query->cursor() as $record) {
            if (! $record instanceof ActivityLogRecord) {
                continue;
            }

            $batch[] = $record;

            if (count($batch) >= $this->chunkSize()) {
                $deleted += $this->deleteBatch($batch);
                $batch = [];
            }
        }

        return $deleted + $this->deleteBatch($batch);
    }

    /**
     * @param  list<ActivityLogRecord>  $records
     */
    private function deleteBatch(array $records): int
    {
        $deleted = 0;

        foreach ($records as $record) {
            $deleted += $record->delete() === true ? 1 : 0;
        }

        return $deleted;
    }

    private function filledOption(string $option): bool
    {
        $value = $this->option($option);

        return $value !== null && $value !== '';
    }

    private function renderError(string $message): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['error' => $message], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return;
        }

        $this->error($message);
    }

    private function renderResult(string $mode, ?string $type, ?CarbonImmutable $cutoff, int $matched, int $deleted): void
    {
        $result = [
            'mode' => $mode,
            'type' => $type,
            'cutoff' => $cutoff?->toIso8601String(),
            'matched' => $matched,
            'deleted' => $deleted,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return;
        }

        $this->line('Matched: '.$matched);
        $this->line('Deleted: '.$deleted);
        $this->line('Mode: '.$mode);
        $this->line('Cutoff: '.($result['cutoff'] ?? 'none'));
    }
}
