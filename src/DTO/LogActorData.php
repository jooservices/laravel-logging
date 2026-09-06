<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\DTO;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use JOOservices\Dto\Core\Dto;

final class LogActorData extends Dto
{
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $id,
    ) {
    }

    public static function fromTarget(Model | Authenticatable | string | null $target): self
    {
        if ($target === null) {
            return new self(null, null);
        }

        if (is_string($target)) {
            return new self($target, null);
        }

        if ($target instanceof Model) {
            return new self($target::class, self::stringKey($target->getKey()));
        }

        return new self($target::class, self::stringKey($target->getAuthIdentifier()));
    }

    public static function external(string $type, string | int | null $id = null): self
    {
        return new self($type, $id === null ? null : (string) $id);
    }

    private static function stringKey(mixed $key): ?string
    {
        return $key === null ? null : (string) $key;
    }
}
