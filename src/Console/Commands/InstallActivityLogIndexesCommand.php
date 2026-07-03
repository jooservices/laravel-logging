<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelLogging\Exceptions\LoggingConfigurationException;
use JOOservices\LaravelLogging\Support\PromotedFieldPromoter;
use MongoDB\Laravel\Connection;

final class InstallActivityLogIndexesCommand extends Command
{
    protected $signature = 'activity-log:indexes';

    protected $description = 'Create MongoDB indexes for the activity_logs collection.';

    public function handle(): int
    {
        $connection = (string) config('laravel-logging.connection', 'mongodb');
        $collectionName = (string) config('laravel-logging.collection', 'activity_logs');
        $database = DB::connection($connection);

        if (! $database instanceof Connection) {
            throw new LoggingConfigurationException("Activity log connection [{$connection}] must be a MongoDB Laravel connection.");
        }

        $collection = $database->getCollection($collectionName);

        foreach (self::expectedIndexes() as $index) {
            $collection->createIndex($index['keys'], $index['options']);
        }

        $this->info("MongoDB indexes ensured for [{$connection}.{$collectionName}].");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{keys: array<string, int>, options: array<string, mixed>}>
     */
    public static function expectedIndexes(): array
    {
        return array_merge([
            ['keys' => ['uuid' => 1], 'options' => ['unique' => true]],
            ['keys' => ['type' => 1], 'options' => []],
            ['keys' => ['adapter' => 1], 'options' => []],
            ['keys' => ['level' => 1], 'options' => []],
            ['keys' => ['action' => 1], 'options' => []],
            ['keys' => ['tenant_id' => 1], 'options' => []],
            ['keys' => ['tenant_id' => 1, 'occurred_at' => -1], 'options' => []],
            ['keys' => ['actor_type' => 1, 'actor_id' => 1], 'options' => []],
            ['keys' => ['subject_type' => 1, 'subject_id' => 1], 'options' => []],
            ['keys' => ['causer_type' => 1, 'causer_id' => 1], 'options' => []],
            ['keys' => ['request_id' => 1], 'options' => []],
            ['keys' => ['correlation_id' => 1], 'options' => []],
            ['keys' => ['trace_id' => 1], 'options' => []],
            ['keys' => ['occurred_at' => 1], 'options' => []],
            ['keys' => ['created_at' => 1], 'options' => []],
            ['keys' => ['type' => 1, 'action' => 1, 'occurred_at' => -1], 'options' => []],
            ['keys' => ['subject_type' => 1, 'subject_id' => 1, 'occurred_at' => -1], 'options' => []],
            ['keys' => ['actor_type' => 1, 'actor_id' => 1, 'occurred_at' => -1], 'options' => []],
        ], PromotedFieldPromoter::indexDefinitions());
    }
}
