<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use MongoDB\Laravel\Connection;
use Throwable;

final class ActivityLogDoctorCommand extends Command
{
    protected $signature = 'activity-log:doctor
        {--json : Output machine-readable JSON}
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
            ...$this->checkAdapters(),
            $this->checkSanitizerConfig(),
            $this->checkQueueConfig(),
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
            return $this->failedResult('config', 'laravel-logging config is not loaded as an array.');
        }

        $connection = config('laravel-logging.connection');
        $collection = config('laravel-logging.collection');

        if (! is_string($connection) || $connection === '' || ! is_string($collection) || $collection === '') {
            return $this->failedResult('config', 'Connection and collection must be non-empty strings.');
        }

        return $this->passedResult('config', "Loaded [{$connection}.{$collection}] activity log config.");
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
                return $this->failedResult('mongodb', "Configured connection [{$connectionName}] is not a MongoDB Laravel connection.");
            }

            $connection->getCollection($collectionName)->countDocuments([], ['limit' => 1]);

            return $this->passedResult('mongodb', "MongoDB collection [{$connectionName}.{$collectionName}] is reachable.");
        } catch (Throwable $exception) {
            return $this->warningResult('mongodb', 'MongoDB reachability check failed: '.$exception->getMessage());
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
        $keys = config('laravel-logging.sanitize.keys');
        $replacement = config('laravel-logging.sanitize.replacement');

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

        return $this->passedResult('sanitize', 'Sanitizer config is valid.');
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkQueueConfig(): array
    {
        $default = config('queue.default');

        if (! is_string($default) || $default === '') {
            return $this->warningResult('queue', 'Queue default driver is not configured.');
        }

        return $this->passedResult('queue', "Queue default driver is [{$default}].");
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkIndexesStatus(): array
    {
        $command = $this->getApplication()?->has('activity-log:indexes') === true;

        if (! $command) {
            return $this->failedResult('indexes', 'activity-log:indexes command is not registered.');
        }

        $connectionName = (string) config('laravel-logging.connection', 'mongodb');
        $collectionName = (string) config('laravel-logging.collection', 'activity_logs');

        try {
            $connection = DB::connection($connectionName);

            if (! $connection instanceof Connection) {
                return $this->failedResult('indexes', "Configured connection [{$connectionName}] is not a MongoDB Laravel connection.");
            }

            $count = iterator_count($connection->getCollection($collectionName)->listIndexes());

            return $this->passedResult('indexes', "Index command is registered; collection currently reports {$count} indexes.");
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
            $this->line((string) json_encode(['checks' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

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
