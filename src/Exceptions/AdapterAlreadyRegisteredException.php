<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Exceptions;

use RuntimeException;

final class AdapterAlreadyRegisteredException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("Log adapter [{$name}] is already registered. Use replace() to override it intentionally.");
    }
}
