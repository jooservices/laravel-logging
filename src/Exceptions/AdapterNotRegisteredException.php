<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Exceptions;

use RuntimeException;

final class AdapterNotRegisteredException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("Log adapter [{$name}] is not registered.");
    }
}
