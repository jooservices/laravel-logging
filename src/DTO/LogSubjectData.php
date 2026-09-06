<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\DTO;

use Illuminate\Database\Eloquent\Model;
use JOOservices\Dto\Core\Dto;

final class LogSubjectData extends Dto
{
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $id,
    ) {
    }

    public static function fromTarget(Model | string | null $target): self
    {
        if ($target === null) {
            return new self(null, null);
        }

        if (is_string($target)) {
            return new self($target, null);
        }

        return new self($target::class, $target->getKey() === null ? null : (string) $target->getKey());
    }

    public static function external(string $type, string | int | null $id = null): self
    {
        return new self($type, $id === null ? null : (string) $id);
    }
}
