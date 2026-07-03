<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperInterface;

final class ReplacementMapperStub implements DomainLogMapperInterface
{
    public function supports(object $event): bool
    {
        return false;
    }

    public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface
    {
        return $adapter;
    }
}
