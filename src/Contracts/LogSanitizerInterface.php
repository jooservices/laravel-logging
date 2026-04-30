<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

interface LogSanitizerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array;
}
