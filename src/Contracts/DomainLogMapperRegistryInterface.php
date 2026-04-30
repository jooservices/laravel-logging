<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

interface DomainLogMapperRegistryInterface
{
    public function register(string|callable $mapper): void;

    public function replace(string $mapperClass, string|callable $mapper): void;

    public function resolveFor(object $event): ?DomainLogMapperInterface;
}
