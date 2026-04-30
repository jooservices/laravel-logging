<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use Illuminate\Contracts\Container\Container;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperRegistryInterface;
use JOOservices\LaravelLogging\Exceptions\InvalidLogAdapterException;

final class DomainLogMapperRegistry implements DomainLogMapperRegistryInterface
{
    /** @var array<string, string|callable> */
    private array $mappers = [];

    public function __construct(private readonly Container $container) {}

    public function register(string|callable $mapper): void
    {
        $this->mappers[$this->key($mapper)] = $mapper;
    }

    public function replace(string $mapperClass, string|callable $mapper): void
    {
        $this->mappers[$mapperClass] = $mapper;
    }

    public function resolveFor(object $event): ?DomainLogMapperInterface
    {
        foreach ($this->mappers as $mapper) {
            $resolved = $this->resolve($mapper);

            if ($resolved->supports($event)) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolve(string|callable $mapper): DomainLogMapperInterface
    {
        $resolved = is_string($mapper) ? $this->container->make($mapper) : $mapper($this->container);

        if (! $resolved instanceof DomainLogMapperInterface) {
            throw new InvalidLogAdapterException('Domain log mapper must implement '.DomainLogMapperInterface::class.'.');
        }

        return $resolved;
    }

    private function key(string|callable $mapper): string
    {
        return is_string($mapper) ? $mapper : spl_object_hash((object) $mapper);
    }
}
