<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use JOOservices\LaravelLogging\Support\RetentionRulePlanner;
use Throwable;

/**
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
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

        if ($this->usesExplicitCutoff()) {
            $plan = $this->plan();

            if ($plan === null) {
                $this->renderResult('dry-run', null, null, 0, 0);

                return self::SUCCESS;
            }

            return $this->pruneSinglePlan($repository, $plan);
        }

        if ((bool) config('laravel-logging.retention.enabled', true) === false) {
            $this->renderResult('dry-run', null, null, 0, 0);

            return self::SUCCESS;
        }

        return $this->pruneDefaultRetention($repository);
    }

    /**
     * @param  array{0: string|null, 1: CarbonImmutable}  $plan
     */
    private function pruneSinglePlan(ActivityLogRepository $repository, array $plan): int
    {
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

    private function pruneDefaultRetention(ActivityLogRepository $repository): int
    {
        $mode = $this->option('force') ? 'force' : 'dry-run';
        $totalMatched = 0;
        $totalDeleted = 0;
        $passes = 0;

        /** @var array<string, int|string> $types */
        $types = (array) config('laravel-logging.retention.types', []);

        foreach ($types as $type => $days) {
            $days = filter_var($days, FILTER_VALIDATE_INT);

            if ($days === false || $days < 1 || ! is_string($type) || $type === '') {
                continue;
            }

            $cutoff = CarbonImmutable::now('UTC')->subDays($days);
            [$matched, $deleted] = $this->pruneTypeCutoff($repository, $type, $cutoff, $mode);
            $totalMatched += $matched;
            $totalDeleted += $deleted;
            $passes++;
        }

        $rules = RetentionRulePlanner::rules();

        foreach ($rules as $rule) {
            [$matched, $deleted] = $this->pruneRule($repository, $rule, $mode);
            $totalMatched += $matched;
            $totalDeleted += $deleted;
            $passes++;
        }

        return $this->renderAggregateResult($mode, $totalMatched, $totalDeleted, $passes);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pruneTypeCutoff(ActivityLogRepository $repository, string $type, CarbonImmutable $cutoff, string $mode): array
    {
        $query = $repository->newQuery()
            ->where('occurred_at', '<', $cutoff)
            ->where('type', $type);

        $matched = (clone $query)->toBase()->count();
        $deleted = $mode === 'force' ? $this->deleteMatchingRecords($query) : 0;

        return [$matched, $deleted];
    }

    /**
     * @param  array{
     *     adapter: string|null,
     *     level: string|null,
     *     action_prefix: string|null,
     *     cutoff: CarbonImmutable
     * }  $rule
     * @return array{0: int, 1: int}
     */
    private function pruneRule(ActivityLogRepository $repository, array $rule, string $mode): array
    {
        $query = $repository->newQuery()->where('occurred_at', '<', $rule['cutoff']);

        if ($rule['adapter'] !== null) {
            $query->where('adapter', $rule['adapter']);
        }

        if ($rule['level'] !== null) {
            $query->where('level', $rule['level']);
        }

        if ($rule['action_prefix'] !== null) {
            $query->where('action', 'like', $rule['action_prefix'].'%');
        }

        $matched = (clone $query)->toBase()->count();
        $deleted = $mode === 'force' ? $this->deleteMatchingRecords($query) : 0;

        return [$matched, $deleted];
    }

    private function renderAggregateResult(string $mode, int $matched, int $deleted, int $passes): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'mode' => $mode,
                'passes' => $passes,
                'matched' => $matched,
                'deleted' => $deleted,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->line('Matched: '.$matched);
        $this->line('Deleted: '.$deleted);
        $this->line('Mode: '.$mode);
        $this->line('Passes: '.$passes);

        return self::SUCCESS;
    }

    private function usesExplicitCutoff(): bool
    {
        return $this->filledOption('days') || $this->filledOption('before') || $this->filledOption('type');
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
