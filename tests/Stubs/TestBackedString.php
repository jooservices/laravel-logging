<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Stubs;

enum TestBackedString: string
{
    case Audit = 'audit';
    case Error = 'error';
    case Boom = 'boom';
    case Cli = 'cli';
}
