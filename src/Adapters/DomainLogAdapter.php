<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use ReflectionClass;

final class DomainLogAdapter extends BaseLogAdapter implements DomainLogAdapterInterface
{
    protected string $type = 'domain';

    protected string $adapter = 'domain';

    protected ?string $level = 'info';

    public function fromEvent(object $event): static
    {
        return $this->project($event);
    }

    public function project(object $event): static
    {
        $shortName = (new ReflectionClass($event))->getShortName();

        return $this
            ->action('domain.'.str($shortName)->snake('.')->toString())
            ->properties(['event' => $event::class]);
    }
}
