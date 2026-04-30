<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;

final class PruneActivityLogsCommand extends Command
{
    protected $signature = 'activity-log:prune
        {--type= : Prune only one log type}
        {--days= : Override retention days}
        {--dry-run : Count matching logs without deleting}
        {--force : Run destructive pruning in production}';

    protected $description = 'Prune activity logs by occurred_at retention rules.';

    public function handle(ActivityLogRepository $repository): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force') && ! $this->option('dry-run')) {
            $this->error('Refusing to prune activity logs in production without --force.');

            return self::FAILURE;
        }

        $plans = $this->plans();

        if ($plans === []) {
            $this->warn('Activity log retention is disabled or no retention rules matched.');

            return self::SUCCESS;
        }

        foreach ($plans as $type => $days) {
            $cutoff = CarbonImmutable::now()->subDays($days);
            $query = $repository->newQuery()
                ->where('type', $type)
                ->where('occurred_at', '<', $cutoff);
            $count = (clone $query)->toBase()->count();

            if ($this->option('dry-run')) {
                $this->line("Would prune {$count} [{$type}] activity logs older than {$days} days.");

                continue;
            }

            $deleted = $query->delete();
            $this->line("Pruned {$deleted} [{$type}] activity logs older than {$days} days.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function plans(): array
    {
        $type = $this->option('type');
        $days = $this->option('days');

        if ($type !== null && $type !== '') {
            $resolvedDays = $days === null || $days === ''
                ? config("laravel-logging.retention.defaults.{$type}")
                : $days;

            return $resolvedDays === null ? [] : [(string) $type => (int) $resolvedDays];
        }

        if ($days !== null && $days !== '') {
            return ['activity' => (int) $days];
        }

        if ((bool) config('laravel-logging.retention.enabled', true) === false) {
            return [];
        }

        /** @var array<string, int> $defaults */
        $defaults = config('laravel-logging.retention.defaults', []);

        return $defaults;
    }
}
