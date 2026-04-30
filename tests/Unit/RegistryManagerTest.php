<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\ActivityLogManager;
use JOOservices\LaravelLogging\Adapters\ActivityLogAdapter;
use JOOservices\LaravelLogging\Adapters\AuditLogAdapter;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Exceptions\AdapterAlreadyRegisteredException;
use JOOservices\LaravelLogging\Exceptions\AdapterNotRegisteredException;
use JOOservices\LaravelLogging\Exceptions\InvalidLogAdapterException;
use JOOservices\LaravelLogging\Exceptions\InvalidLogDataException;
use JOOservices\LaravelLogging\LogAdapterRegistry;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;
use JOOservices\LaravelLogging\Tests\TestCase;
use stdClass;

final class RegistryManagerTest extends TestCase
{
    public function test_can_register_and_resolve_adapter(): void
    {
        $registry = new LogAdapterRegistry($this->app);
        $registry->register('custom', ActivityLogAdapter::class);

        $this->assertInstanceOf(ActivityLogAdapter::class, $registry->resolve('custom'));
    }

    public function test_duplicate_register_throws(): void
    {
        $registry = new LogAdapterRegistry($this->app);
        $registry->register('audit', AuditLogAdapter::class);

        $this->expectException(AdapterAlreadyRegisteredException::class);
        $registry->register('AUDIT', AuditLogAdapter::class);
    }

    public function test_replace_overrides_intentionally(): void
    {
        $registry = new LogAdapterRegistry($this->app);
        $registry->register('custom', ActivityLogAdapter::class);
        $registry->replace('custom', AuditLogAdapter::class);

        $this->assertInstanceOf(AuditLogAdapter::class, $registry->resolve('custom'));
    }

    public function test_missing_adapter_throws(): void
    {
        $registry = new LogAdapterRegistry($this->app);

        $this->expectException(AdapterNotRegisteredException::class);
        $registry->resolve('missing');
    }

    public function test_wrong_adapter_type_throws(): void
    {
        $registry = new LogAdapterRegistry($this->app);
        $registry->register('wrong', fn (): stdClass => new stdClass);

        $this->expectException(InvalidLogAdapterException::class);
        $registry->resolve('wrong');
    }

    public function test_manager_magic_resolves_adapter_and_rejects_parameters(): void
    {
        /** @var LogAdapterRegistryInterface $registry */
        $registry = $this->app->make(LogAdapterRegistryInterface::class);
        $manager = new ActivityLogManager($registry, $this->app->make(ActivityLogRepository::class));

        $this->assertInstanceOf(LogAdapterInterface::class, $manager->activity());

        $this->expectException(InvalidLogDataException::class);
        $manager->activity('unexpected');
    }

    public function test_adapter_resolve_returns_fresh_stateful_instances(): void
    {
        /** @var LogAdapterRegistryInterface $registry */
        $registry = $this->app->make(LogAdapterRegistryInterface::class);

        $first = $registry->resolve('activity')->action('one');
        $second = $registry->resolve('activity');

        $this->assertNotSame($first, $second);

        $this->expectException(InvalidLogDataException::class);
        $second->toData();
    }
}
