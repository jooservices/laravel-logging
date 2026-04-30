<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use Illuminate\Support\ServiceProvider;
use JOOservices\LaravelLogging\Console\Commands\InstallActivityLogIndexesCommand;
use JOOservices\LaravelLogging\Contracts\ActivityLogManagerInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Services\DefaultLogContextResolver;
use JOOservices\LaravelLogging\Services\DefaultLogSanitizer;

final class LaravelLoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-logging.php', 'laravel-logging');

        $this->app->singleton(LogSanitizerInterface::class, function (): DefaultLogSanitizer {
            /** @var array<int, string> $keys */
            $keys = config('laravel-logging.sanitize.keys', []);

            return new DefaultLogSanitizer(
                keys: $keys,
                replacement: (string) config('laravel-logging.sanitize.replacement', '[REDACTED]'),
            );
        });

        $this->app->singleton(LogContextResolverInterface::class, DefaultLogContextResolver::class);
        $this->app->singleton(LogStoreInterface::class, (string) config('laravel-logging.store'));
        $this->app->singleton(LogAdapterRegistryInterface::class, LogAdapterRegistry::class);
        $this->app->singleton(ActivityLogManagerInterface::class, ActivityLogManager::class);
        $this->app->singleton(ActivityLogManager::class, ActivityLogManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-logging.php' => config_path('laravel-logging.php'),
        ], 'laravel-logging-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallActivityLogIndexesCommand::class]);
        }

        /** @var LogAdapterRegistryInterface $registry */
        $registry = $this->app->make(LogAdapterRegistryInterface::class);

        /** @var array<string, string|callable> $adapters */
        $adapters = config('laravel-logging.adapters', []);

        foreach ($adapters as $name => $adapter) {
            $registry->replace($name, $adapter);
        }
    }
}
