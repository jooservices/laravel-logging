<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\DTO;

use JOOservices\Dto\Core\Dto;

/**
 * Pure identity value. Build from LogIdentity — never pass Eloquent models here.
 */
final class LogActorData extends Dto
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $id = null,
    ) {
    }
}
