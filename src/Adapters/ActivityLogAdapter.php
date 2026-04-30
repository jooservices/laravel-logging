<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use JOOservices\LaravelLogging\Contracts\ActivityLogAdapterInterface;

final class ActivityLogAdapter extends BaseLogAdapter implements ActivityLogAdapterInterface
{
    protected string $type = 'activity';

    protected string $adapter = 'activity';

    protected ?string $level = 'info';
}
