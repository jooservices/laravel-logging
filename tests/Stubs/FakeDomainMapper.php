<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Stubs;

use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperInterface;

final class FakeDomainMapper implements DomainLogMapperInterface
{
    public function supports(object $event): bool
    {
        return true;
    }

    public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface
    {
        return $adapter;
    }
}
