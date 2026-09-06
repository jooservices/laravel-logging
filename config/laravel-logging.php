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

    /*
    |--------------------------------------------------------------------------
    | Promoted fields
    |--------------------------------------------------------------------------
    |
    | Copy nested values from properties/context onto top-level document fields
    | before persistence so MongoDB indexes can target them directly.
    |
    | Example: 'site_id' => 'properties.site_id'
    |
    */
    'promoted_fields' => [
        'batch_id' => 'context.batch_id',
        'workflow_id' => 'context.workflow_id',
        // 'site_id' => 'properties.site_id',
    ],

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
        /*
        |--------------------------------------------------------------------------
        | Granular retention rules
        |--------------------------------------------------------------------------
        |
        | Each rule matches logs by adapter, level, and/or action_prefix, then
        | prunes records older than retention_days. Rules run during the default
        | prune pass (no explicit --type/--days/--before options).
        |
        */
        'rules' => [
            // [
            //     'adapter' => 'system',
            //     'level' => 'debug',
            //     'action_prefix' => 'http.',
            //     'retention_days' => 14,
            // ],
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
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'current_password',
            'token',
            'access_token',
            'accessToken',
            'refresh_token',
            'secret',
            'client_secret',
            'api_key',
            'apiKey',
            'apikey',
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
        ],
        'sensitive_patterns' => [],
        // Value patterns applied to string leaves (Bearer tokens, JWT, PEM blocks).
        'value_patterns' => [
            '/(?i)^Bearer\s+[A-Za-z0-9\-._~+\/=]+$/',
            '/(?i)^eyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/',
            '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        ],
    ],

    'limits' => [
        'enabled' => true,
        'max_string_length' => 5000,
        'max_array_items' => 200,
        'max_depth' => 8,
        'max_document_bytes' => 524288,
        'truncate_marker' => '[truncated]',
    ],

    /*
    |--------------------------------------------------------------------------
    | TTL indexes (optional MongoDB expireAfterSeconds)
    |--------------------------------------------------------------------------
    |
    | When enabled, activity-log:indexes creates a TTL index on occurred_at.
    | Prefer command-based prune for type-aware retention; use TTL only for
    | coarse maximum lifetime of the collection.
    |
    */
    'ttl' => [
        'enabled' => (bool) env('ACTIVITY_LOG_TTL_ENABLED', false),
        'expire_after_days' => (int) env('ACTIVITY_LOG_TTL_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP request logging middleware (opt-in)
    |--------------------------------------------------------------------------
    |
    | Register JOOservices\LaravelLogging\Http\Middleware\LogHttpRequest in
    | your application middleware stack. Disabled by default.
    |
    */
    'http' => [
        'enabled' => (bool) env('ACTIVITY_LOG_HTTP_ENABLED', false),
        'action' => 'http.request',
        'level' => 'info',
        'queue' => (bool) env('ACTIVITY_LOG_HTTP_QUEUE', true),
        'ignore_paths' => [
            'up',
            'health',
            'horizon*',
            'telescope*',
            'login',
            'logout',
            'register',
            'password/*',
            'reset-password*',
            'forgot-password*',
            'sanctum/*',
            'oauth/*',
        ],
    ],
];
