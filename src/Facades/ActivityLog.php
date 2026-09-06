<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Facades;

use BackedEnum;
use Illuminate\Support\Facades\Facade;
use JOOservices\LaravelLogging\ActivityLogManager as Manager;
use JOOservices\LaravelLogging\ActivityLogQuery;
use JOOservices\LaravelLogging\Contracts\ActivityLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\AuditLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\SecurityLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\SystemLogAdapterInterface;

/**
 * @method static ActivityLogAdapterInterface activity()
 * @method static AuditLogAdapterInterface audit()
 * @method static SecurityLogAdapterInterface security()
 * @method static DomainLogAdapterInterface domain()
 * @method static SystemLogAdapterInterface system()
 * @method static LogAdapterInterface adapter(string|BackedEnum $name)
 * @method static ActivityLogQuery query()
 * @method static ActivityLogQuery records()
 * @method static \Illuminate\Support\Collection recordMany(array $records)
 * @method static void register(string|BackedEnum $name, string|callable $adapter)
 * @method static void replace(string|BackedEnum $name, string|callable $adapter)
 * @method static bool hasAdapter(string|BackedEnum $name)
 * @method static array<string, string|callable> adapters()
 *
 * @see Manager
 */
final class ActivityLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
