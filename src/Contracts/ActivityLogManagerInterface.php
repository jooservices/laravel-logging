<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use BackedEnum;

interface ActivityLogManagerInterface
{
    public function adapter(string|BackedEnum $name): LogAdapterInterface;

    public function query(): \JOOservices\LaravelLogging\ActivityLogQuery;

    public function records(): \JOOservices\LaravelLogging\ActivityLogQuery;

    public function register(string|BackedEnum $name, string|callable $adapter): void;

    public function replace(string|BackedEnum $name, string|callable $adapter): void;

    public function hasAdapter(string|BackedEnum $name): bool;

    /**
     * @return array<string, string|callable>
     */
    public function adapters(): array;

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): LogAdapterInterface;
}
