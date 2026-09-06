<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

interface ActivityLogPayloadLimiterInterface
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function limit(array $payload): array;
}
