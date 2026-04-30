<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use BackedEnum;

interface LogAdapterRegistryInterface
{
    public function register(string|BackedEnum $name, string|callable $adapter): void;

    public function replace(string|BackedEnum $name, string|callable $adapter): void;

    public function resolve(string|BackedEnum $name): LogAdapterInterface;

    public function has(string|BackedEnum $name): bool;

    /**
     * @return array<string, string|callable>
     */
    public function all(): array;
}
