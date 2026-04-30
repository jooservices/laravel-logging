<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

interface DomainLogMapperInterface
{
    public function supports(object $event): bool;

    public function map(object $event, DomainLogAdapterInterface $adapter): DomainLogAdapterInterface;
}
