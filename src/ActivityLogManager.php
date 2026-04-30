<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use BackedEnum;
use JOOservices\LaravelLogging\Contracts\ActivityLogManagerInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterRegistryInterface;
use JOOservices\LaravelLogging\Exceptions\InvalidLogDataException;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;

final class ActivityLogManager implements ActivityLogManagerInterface
{
    public function __construct(
        private readonly LogAdapterRegistryInterface $registry,
        private readonly ActivityLogRepository $repository,
    ) {}

    public function adapter(string|BackedEnum $name): LogAdapterInterface
    {
        return $this->registry->resolve($name);
    }

    public function query(): ActivityLogQuery
    {
        return new ActivityLogQuery($this->repository);
    }

    public function records(): ActivityLogQuery
    {
        return $this->query();
    }

    public function register(string|BackedEnum $name, string|callable $adapter): void
    {
        $this->registry->register($name, $adapter);
    }

    public function replace(string|BackedEnum $name, string|callable $adapter): void
    {
        $this->registry->replace($name, $adapter);
    }

    public function hasAdapter(string|BackedEnum $name): bool
    {
        return $this->registry->has($name);
    }

    public function adapters(): array
    {
        return $this->registry->all();
    }

    public function __call(string $method, array $parameters): LogAdapterInterface
    {
        if ($parameters !== []) {
            throw new InvalidLogDataException("Log adapter method [{$method}] does not accept parameters. Use adapter('{$method}') for explicit resolution.");
        }

        return $this->adapter($method);
    }
}
