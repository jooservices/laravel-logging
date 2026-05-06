<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelLogging\ActivityLogManager;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use MongoDB\Laravel\Connection;
use Throwable;

/**
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final class ActivityLogDoctorCommand extends Command
{
    protected $signature = 'activity-log:doctor
        {--json : Output machine-readable JSON}
        {--check-indexes : Verify expected MongoDB indexes exist}
        {--strict : Treat warnings as failures}';

    protected $description = 'Inspect activity log configuration, bindings, adapters, and MongoDB readiness.';

    public function handle(): int
    {
        $checks = [
            $this->checkConfigLoaded(),
            $this->checkMongoConnection(),
            $this->checkModel(),
            $this->checkRepository(),
            $this->checkStore(),
            $this->checkManager(),
            ...$this->checkAdapters(),
            $this->checkSanitizerConfig(),
            $this->checkPayloadLimitConfig(),
            $this->checkRetentionConfig(),
            $this->checkIndexesStatus(),
        ];

        $this->renderChecks($checks);

        return $this->exitCode($checks);
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkConfigLoaded(): array
    {
        $config = config('laravel-logging');

        if (! is_array($config)) {
            return $this->failedResult('config.loaded', 'laravel-logging config is not loaded as an array.');
        }

        $connection = config('laravel-logging.connection');
        $collection = config('laravel-logging.collection');

        if (! is_string($connection) || $connection === '' || ! is_string($collection) || $collection === '') {
            return $this->failedResult('config.storage', 'Connection and collection must be non-empty strings.');
        }

        return $this->passedResult('config.loaded', "Loaded [{$connection}.{$collection}] activity log config.");
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkMongoConnection(): array
    {
        $connectionName = (string) config('laravel-logging.connection', 'mongodb');
        $collectionName = (string) config('laravel-logging.collection', 'activity_logs');

        try {
            $connection = DB::connection($connectionName);

            if (! $connection instanceof Connection) {
                return $this->failedResult('mongodb.connection', "Configured connection [{$connectionName}] is not a MongoDB Laravel connection.");
            }

            $connection->getCollection($collectionName)->countDocuments([], ['limit' => 1]);

            return $this->passedResult('mongodb.connection', "MongoDB collection [{$connectionName}.{$collectionName}] is reachable.");
        } catch (Throwable $exception) {
            return $this->failedResult('mongodb.connection', 'MongoDB reachability check failed: '.$exception->getMessage());
        }
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkModel(): array
    {
        try {
            $model = $this->laravel->make(ActivityLogRecord::class);

            if (! $model instanceof ActivityLogRecord) {
                return $this->failedResult('model', 'ActivityLogRecord binding did not resolve an ActivityLogRecord instance.');
            }

            return $this->passedResult('model', 'Resolved '.get_class($model).'.');
        } catch (Throwable $exception) {
            return $this->failedResult('model', $exception->getMessage());
        }
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkRepository(): array
    {
        try {
            $repository = $this->laravel->make(ActivityLogRepository::class);

            if (! $repository instanceof ActivityLogRepository) {
                return $this->failedResult('repository', 'ActivityLogRepository binding did not resolve the internal repository.');
            }

            return $this->passedResult('repository', 'Resolved '.get_class($repository).' with model '.get_class($repository->getModel()).'.');
        } catch (Throwable $exception) {
            return $this->failedResult('repository', $exception->getMessage());
        }
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkStore(): array
    {
        try {
            $store = $this->laravel->make(LogStoreInterface::class);

            if (! $store instanceof LogStoreInterface) {
                return $this->failedResult('store', 'LogStoreInterface binding resolved an invalid store instance.');
            }

            return $this->passedResult('store', 'Resolved '.get_class($store).'.');
        } catch (Throwable $exception) {
            return $this->failedResult('store', $exception->getMessage());
        }
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkManager(): array
    {
        try {
            $manager = $this->laravel->make(ActivityLogManager::class);

            if (! $manager instanceof ActivityLogManager) {
                return $this->failedResult('manager', 'ActivityLogManager binding resolved an invalid manager instance.');
            }

            return $this->passedResult('manager', 'Resolved '.get_class($manager).'.');
        } catch (Throwable $exception) {
            return $this->failedResult('manager', $exception->getMessage());
        }
    }

    /**
     * @return list<array{name: string, status: string, message: string}>
     */
    private function checkAdapters(): array
    {
        try {
            /** @var LogAdapterRegistryInterface $registry */
            $registry = $this->laravel->make(LogAdapterRegistryInterface::class);
            $adapters = $registry->all();

            if ($adapters === []) {
                return [$this->warningResult('adapters', 'No adapters are registered in the registry.')];
            }

            $checks = [];

            foreach (array_keys($adapters) as $name) {
                try {
                    $adapter = $registry->resolve($name);

                    if (! $adapter instanceof LogAdapterInterface) {
                        $checks[] = $this->failedResult("adapter:{$name}", 'Resolved adapter does not implement LogAdapterInterface.');

                        continue;
                    }

                    $checks[] = $this->passedResult("adapter:{$name}", 'Resolved '.get_class($adapter).'.');
                } catch (Throwable $exception) {
                    $checks[] = $this->failedResult("adapter:{$name}", $exception->getMessage());
                }
            }

            return $checks;
        } catch (Throwable $exception) {
            return [$this->failedResult('adapters', $exception->getMessage())];
        }
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkSanitizerConfig(): array
    {
        $keys = config('laravel-logging.sanitization.sensitive_keys', config('laravel-logging.sanitize.keys'));
        $replacement = config('laravel-logging.sanitization.redacted_value', config('laravel-logging.sanitize.replacement'));
        $patterns = config('laravel-logging.sanitization.sensitive_patterns', []);

        if (! is_array($keys)) {
            return $this->failedResult('sanitize', 'Sanitizer keys must be configured as an array of strings.');
        }

        foreach ($keys as $key) {
            if (! is_string($key) || $key === '') {
                return $this->failedResult('sanitize', 'Sanitizer keys must contain only non-empty strings.');
            }
        }

        if (! is_string($replacement) || $replacement === '') {
            return $this->failedResult('sanitize', 'Sanitizer replacement must be a non-empty string.');
        }

        if (! is_array($patterns)) {
            return $this->failedResult('sanitize', 'Sensitive patterns must be an array.');
        }

        return $this->passedResult('sanitize', 'Sanitizer config is valid.');
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkPayloadLimitConfig(): array
    {
        try {
            $limiter = $this->laravel->make(ActivityLogPayloadLimiterInterface::class);

            if (! $limiter instanceof ActivityLogPayloadLimiterInterface) {
                return $this->failedResult('limits', 'Payload limiter binding resolved an invalid limiter instance.');
            }
        } catch (Throwable $exception) {
            return $this->failedResult('limits', $exception->getMessage());
        }

        foreach (['max_string_length', 'max_array_items', 'max_depth', 'max_document_bytes'] as $key) {
            $value = config("laravel-logging.limits.{$key}");

            if (! is_int($value) || $value < 1) {
                return $this->failedResult('limits', "Payload limit [{$key}] must be a positive integer.");
            }
        }

        return $this->passedResult('limits', 'Payload limit config is valid.');
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkRetentionConfig(): array
    {
        $types = config('laravel-logging.retention.types', config('laravel-logging.retention.defaults', []));
        $chunk = config('laravel-logging.retention.chunk_size');

        if (! is_array($types)) {
            return $this->failedResult('retention', 'Retention types must be an array.');
        }

        foreach ($types as $type => $days) {
            if (! is_string($type) || ! is_int($days) || $days < 1) {
                return $this->failedResult('retention', 'Retention type rules must map string types to positive integer days.');
            }
        }

        if (! is_int($chunk) || $chunk < 1) {
            return $this->failedResult('retention', 'Retention chunk size must be a positive integer.');
        }

        return $this->passedResult('retention', 'Retention config is valid.');
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkIndexesStatus(): array
    {
        $command = Artisan::all()['activity-log:indexes'] ?? null;

        if (! $command) {
            return $this->failedResult('indexes', 'activity-log:indexes command is not registered.');
        }

        if (! $this->option('check-indexes')) {
            return $this->passedResult('indexes', 'activity-log:indexes command is registered.');
        }

        $connectionName = (string) config('laravel-logging.connection', 'mongodb');
        $collectionName = (string) config('laravel-logging.collection', 'activity_logs');

        try {
            $connection = DB::connection($connectionName);

            if (! $connection instanceof Connection) {
                return $this->failedResult('indexes', "Configured connection [{$connectionName}] is not a MongoDB Laravel connection.");
            }

            $indexes = iterator_to_array($connection->getCollection($collectionName)->listIndexes());
            $existing = array_map(static fn (mixed $index): array => $index->getKey(), $indexes);
            $missing = [];

            foreach (InstallActivityLogIndexesCommand::expectedIndexes() as $index) {
                if (! in_array($index['keys'], $existing, true)) {
                    $missing[] = json_encode($index['keys'], JSON_THROW_ON_ERROR);
                }
            }

            if ($missing !== []) {
                return $this->warningResult('indexes', 'Missing expected indexes: '.implode(', ', $missing).'. Run php artisan activity-log:indexes.');
            }

            return $this->passedResult('indexes', 'All expected activity log indexes are present.');
        } catch (Throwable $exception) {
            return $this->warningResult('indexes', 'Index status check could not inspect the collection: '.$exception->getMessage());
        }
    }

    /**
     * @param  list<array{name: string, status: string, message: string}>  $checks
     */
    private function renderChecks(array $checks): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status' => $this->summaryStatus($checks),
                'checks' => $checks,
            ], JSON_THROW_ON_ERROR));

            return;
        }

        $this->table(
            ['Check', 'Status', 'Message'],
            array_map(
                fn (array $check): array => [$check['name'], strtoupper($check['status']), $check['message']],
                $checks,
            ),
        );
    }

    /**
     * @param  list<array{name: string, status: string, message: string}>  $checks
     */
    private function exitCode(array $checks): int
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                return self::FAILURE;
            }

            if ($check['status'] === 'warn' && $this->option('strict')) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{name: string, status: string, message: string}>  $checks
     */
    private function summaryStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('fail', $statuses, true)) {
            return 'fail';
        }

        if (in_array('warn', $statuses, true)) {
            return 'warn';
        }

        return 'pass';
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function passedResult(string $name, string $message): array
    {
        return ['name' => $name, 'status' => 'pass', 'message' => $message];
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function warningResult(string $name, string $message): array
    {
        return ['name' => $name, 'status' => 'warn', 'message' => $message];
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function failedResult(string $name, string $message): array
    {
        return ['name' => $name, 'status' => 'fail', 'message' => $message];
    }
}
