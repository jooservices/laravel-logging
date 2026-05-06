<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use Illuminate\Support\ServiceProvider;
use JOOservices\LaravelLogging\Console\Commands\ActivityLogDoctorCommand;
use JOOservices\LaravelLogging\Console\Commands\ExportActivityLogsCommand;
use JOOservices\LaravelLogging\Console\Commands\InstallActivityLogIndexesCommand;
use JOOservices\LaravelLogging\Console\Commands\PruneActivityLogsCommand;
use JOOservices\LaravelLogging\Contracts\ActivityLogManagerInterface;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Exceptions\LoggingConfigurationException;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use JOOservices\LaravelLogging\Services\ActivityLogPayloadLimiter;
use JOOservices\LaravelLogging\Services\DefaultLogContextResolver;
use JOOservices\LaravelLogging\Services\DefaultLogSanitizer;

final class LaravelLoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-logging.php', 'laravel-logging');

        $this->app->singleton(LogSanitizerInterface::class, function (): DefaultLogSanitizer {
            /** @var array<int, string> $keys */
            $keys = config('laravel-logging.sanitization.sensitive_keys', config('laravel-logging.sanitize.keys', []));
            /** @var array<int, string> $patterns */
            $patterns = config('laravel-logging.sanitization.sensitive_patterns', []);

            return new DefaultLogSanitizer(
                keys: $keys,
                replacement: (string) config('laravel-logging.sanitization.redacted_value', config('laravel-logging.sanitize.replacement', '[redacted]')),
                enabled: (bool) config('laravel-logging.sanitization.enabled', true),
                caseSensitive: (bool) config('laravel-logging.sanitization.case_sensitive', false),
                patterns: $patterns,
            );
        });

        $this->app->singleton(ActivityLogPayloadLimiterInterface::class, function (): ActivityLogPayloadLimiter {
            /** @var array<string, mixed> $config */
            $config = config('laravel-logging.limits', []);

            return new ActivityLogPayloadLimiter($config);
        });

        $this->app->bind(ActivityLogRecord::class, function (): ActivityLogRecord {
            $model = (string) config('laravel-logging.model', ActivityLogRecord::class);

            if ($model !== ActivityLogRecord::class && ! is_subclass_of($model, ActivityLogRecord::class)) {
                throw new LoggingConfigurationException('Configured activity log model must extend '.ActivityLogRecord::class.'.');
            }

            return new $model;
        });

        $this->app->singleton(ActivityLogRepository::class, function (): ActivityLogRepository {
            return new ActivityLogRepository($this->app->make(ActivityLogRecord::class));
        });

        $this->app->singleton(LogContextResolverInterface::class, DefaultLogContextResolver::class);
        $this->app->singleton(LogStoreInterface::class, (string) config('laravel-logging.store'));
        $this->app->singleton(LogAdapterRegistryInterface::class, LogAdapterRegistry::class);
        $this->app->singleton(DomainLogMapperRegistryInterface::class, DomainLogMapperRegistry::class);
        $this->app->singleton(ActivityLogManagerInterface::class, ActivityLogManager::class);
        $this->app->singleton(ActivityLogManager::class, ActivityLogManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-logging.php' => config_path('laravel-logging.php'),
        ], 'laravel-logging-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ActivityLogDoctorCommand::class,
                ExportActivityLogsCommand::class,
                InstallActivityLogIndexesCommand::class,
                PruneActivityLogsCommand::class,
            ]);
        }

        /** @var LogAdapterRegistryInterface $registry */
        $registry = $this->app->make(LogAdapterRegistryInterface::class);

        /** @var array<string, string|callable> $adapters */
        $adapters = config('laravel-logging.adapters', []);

        foreach ($adapters as $name => $adapter) {
            $registry->replace($name, $adapter);
        }

        /** @var DomainLogMapperRegistryInterface $mapperRegistry */
        $mapperRegistry = $this->app->make(DomainLogMapperRegistryInterface::class);

        /** @var array<int|string, string|callable> $mappers */
        $mappers = config('laravel-logging.domain_mappers', []);

        foreach ($mappers as $mapper) {
            $mapperRegistry->register($mapper);
        }
    }
}
