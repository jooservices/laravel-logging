<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use BackedEnum;
use Illuminate\Contracts\Container\Container;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Exceptions\AdapterAlreadyRegisteredException;
use JOOservices\LaravelLogging\Exceptions\AdapterNotRegisteredException;
use JOOservices\LaravelLogging\Exceptions\InvalidLogAdapterException;

final class LogAdapterRegistry implements LogAdapterRegistryInterface
{
    /**
     * @param  array<string, string|callable>  $adapters
     */
    public function __construct(
        private readonly Container $container,
        private array $adapters = [],
    ) {
    }

    public function register(string | BackedEnum $name, string | callable $adapter): void
    {
        $name = $this->normalize($name);

        if (array_key_exists($name, $this->adapters)) {
            throw AdapterAlreadyRegisteredException::forName($name);
        }

        $this->adapters[$name] = $adapter;
    }

    public function replace(string | BackedEnum $name, string | callable $adapter): void
    {
        $this->adapters[$this->normalize($name)] = $adapter;
    }

    public function resolve(string | BackedEnum $name): LogAdapterInterface
    {
        $normalized = $this->normalize($name);

        if (! array_key_exists($normalized, $this->adapters)) {
            throw AdapterNotRegisteredException::forName($normalized);
        }

        $adapter = $this->adapters[$normalized];
        $resolved = is_string($adapter)
            ? $this->container->make($adapter)
            : $this->container->call($adapter);

        if (! $resolved instanceof LogAdapterInterface) {
            throw InvalidLogAdapterException::forName($normalized);
        }

        return $resolved;
    }

    public function has(string | BackedEnum $name): bool
    {
        return array_key_exists($this->normalize($name), $this->adapters);
    }

    public function all(): array
    {
        return $this->adapters;
    }

    private function normalize(string | BackedEnum $name): string
    {
        $name = $name instanceof BackedEnum ? (string) $name->value : $name;

        return strtolower(trim($name));
    }
}
