<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests;

use Illuminate\Support\Facades\DB;
use JOOservices\LaravelLogging\LaravelLoggingServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Throwable;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MongoDBServiceProvider::class,
            LaravelLoggingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:3n8+o8v8LxQ5p5QnMei8zyfY4m4Q7vHf+9v1W8o5H9Q=');
        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_URI', 'mongodb://localhost:27017'),
            'database' => env('MONGODB_DATABASE', 'jooservices_logging'),
        ]);
        $app['config']->set('session.driver', 'array');
    }

    protected function clearActivityLogs(): void
    {
        $this->clearCollection('activity_logs');
    }

    protected function clearCollection(string $collection): void
    {
        DB::connection('mongodb')->getCollection($collection)->deleteMany([]);
    }

    protected function requiresMongoDb(): void
    {
        try {
            DB::connection('mongodb')->getCollection('activity_logs')->countDocuments([], ['limit' => 1]);
        } catch (Throwable $exception) {
            $this->markTestSkipped('MongoDB integration tests require a running MongoDB server at ' . env('MONGODB_URI', 'mongodb://localhost:27017') . '. ' . $exception->getMessage());
        }
    }
}
