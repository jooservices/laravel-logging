<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\DomainLogMapperRegistryInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use ReflectionClass;

final class DomainLogAdapter extends BaseLogAdapter implements DomainLogAdapterInterface
{
    protected string $type = 'domain';

    protected string $adapter = 'domain';

    protected ?string $level = 'info';

    public function __construct(
        LogStoreInterface $store,
        LogSanitizerInterface $sanitizer,
        ActivityLogPayloadLimiterInterface $payloadLimiter,
        LogContextResolverInterface $contextResolver,
        private readonly ?DomainLogMapperRegistryInterface $mapperRegistry = null,
    ) {
        parent::__construct($store, $sanitizer, $payloadLimiter, $contextResolver);
    }

    public function fromEvent(object $event): static
    {
        $mapper = $this->mapperRegistry?->resolveFor($event);

        if ($mapper !== null) {
            $mapped = $mapper->map($event, $this);

            if ($mapped instanceof self) {
                return $mapped;
            }

            return $this;
        }

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
