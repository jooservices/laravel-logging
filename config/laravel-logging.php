<?php

declare(strict_types=1);

use JOOservices\LaravelLogging\Adapters\ActivityLogAdapter;
use JOOservices\LaravelLogging\Adapters\AuditLogAdapter;
use JOOservices\LaravelLogging\Adapters\DomainLogAdapter;
use JOOservices\LaravelLogging\Adapters\SecurityLogAdapter;
use JOOservices\LaravelLogging\Adapters\SystemLogAdapter;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Stores\MongoLogStore;

return [
    'connection' => env('ACTIVITY_LOG_CONNECTION', 'mongodb'),
    'collection' => env('ACTIVITY_LOG_COLLECTION', 'activity_logs'),

    'model' => ActivityLogRecord::class,
    'store' => MongoLogStore::class,

    'adapters' => [
        'activity' => ActivityLogAdapter::class,
        'audit' => AuditLogAdapter::class,
        'security' => SecurityLogAdapter::class,
        'domain' => DomainLogAdapter::class,
        'system' => SystemLogAdapter::class,
    ],

    'domain_mappers' => [],

    'retention' => [
        'enabled' => true,
        'defaults' => [
            'activity' => 90,
            'audit' => 365,
            'security' => 365,
            'domain' => 90,
            'system' => 30,
        ],
    ],

    'sanitize' => [
        'keys' => [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'authorization',
            'cookie',
        ],
        'replacement' => '[REDACTED]',
    ],
];
