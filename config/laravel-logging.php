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
        'default_days' => 180,
        'chunk_size' => 500,
        'types' => [
            'activity' => 90,
            'audit' => 365,
            'security' => 365,
            'domain' => 90,
            'system' => 30,
        ],
    ],

    'export' => [
        'chunk_size' => 500,
        'formats' => ['jsonl', 'csv'],
    ],

    'sanitization' => [
        'enabled' => true,
        'case_sensitive' => false,
        'redacted_value' => '[redacted]',
        'sensitive_keys' => [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'client_secret',
            'api_key',
            'apikey',
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
        ],
        'sensitive_patterns' => [],
    ],

    'limits' => [
        'enabled' => true,
        'max_string_length' => 5000,
        'max_array_items' => 200,
        'max_depth' => 8,
        'max_document_bytes' => 524288,
        'truncate_marker' => '[truncated]',
    ],
];
