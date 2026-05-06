<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

interface ActivityLogPayloadLimiterInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function limit(array $payload): array;
}
