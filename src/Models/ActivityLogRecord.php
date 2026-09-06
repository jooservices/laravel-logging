<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $_id
 * @property string $uuid
 * @property string $type
 * @property string $adapter
 * @property string|null $level
 * @property string $action
 * @property string|null $message
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $causer_type
 * @property string|null $causer_id
 * @property string|null $source
 * @property string|null $source_type
 * @property string|null $request_id
 * @property string|null $correlation_id
 * @property string|null $trace_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $tenant_id
 * @property string|null $batch_id
 * @property string|null $workflow_id
 * @property array<string, mixed> $properties
 * @property array<string, mixed> $context
 * @property array<string, mixed> $changes
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ActivityLogRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
        'context' => 'array',
        'changes' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('laravel-logging.connection', 'mongodb');
    }

    public function getTable()
    {
        return config('laravel-logging.collection', 'activity_logs');
    }
}
