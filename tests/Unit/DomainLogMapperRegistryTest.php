<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperInterface;
use JOOservices\LaravelLogging\DomainLogMapperRegistry;
use JOOservices\LaravelLogging\Exceptions\InvalidLogAdapterException;
use JOOservices\LaravelLogging\Tests\TestCase;
use stdClass;

final class DomainLogMapperRegistryTest extends TestCase
{
    public function test_resolve_for_returns_matching_mapper(): void
    {
        $event = new class {};

        $registry = new DomainLogMapperRegistry($this->app);
        $registry->register(fn (): DomainLogMapperInterface => new class implements DomainLogMapperInterface
        {
            public function supports(object $event): bool
            {
                return true;
            }

            public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface
            {
                return $adapter->action('mapped');
            }
        });

        $mapper = $registry->resolveFor($event);

        $this->assertNotNull($mapper);
        $this->assertTrue($mapper->supports($event));
    }

    public function test_replace_swaps_registered_mapper(): void
    {
        $registry = new DomainLogMapperRegistry($this->app);
        $registry->register(ReplacementMapperStub::class);
        $registry->replace(ReplacementMapperStub::class, fn (): DomainLogMapperInterface => new class implements DomainLogMapperInterface
        {
            public function supports(object $event): bool
            {
                return true;
            }

            public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface
            {
                return $adapter->action('second');
            }
        });

        $mapper = $registry->resolveFor(new stdClass);

        $this->assertNotNull($mapper);
        $this->assertTrue($mapper->supports(new stdClass));
    }

    public function test_invalid_mapper_binding_throws(): void
    {
        $registry = new DomainLogMapperRegistry($this->app);
        $registry->register(fn (): stdClass => new stdClass);

        $this->expectException(InvalidLogAdapterException::class);
        $registry->resolveFor(new stdClass);
    }
}
