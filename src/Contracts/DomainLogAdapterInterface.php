<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

interface DomainLogAdapterInterface extends LogAdapterInterface
{
    public function fromEvent(object $event): static;

    public function project(object $event): static;
}
