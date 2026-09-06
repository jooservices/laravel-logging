<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

interface LogSanitizerInterface
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function sanitize(array $payload): array;
}
