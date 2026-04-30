<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Exceptions;

use RuntimeException;

final class InvalidLogAdapterException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("Resolved log adapter [{$name}] must implement LogAdapterInterface.");
    }
}
