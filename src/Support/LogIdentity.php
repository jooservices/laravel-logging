<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class LogIdentity
{
    /**
     * @return array{type: string|null, id: string|null}
     */
    public static function actor(Model | Authenticatable | string | null $target, string | int | null $id = null): array
    {
        if ($target === null) {
            return ['type' => null, 'id' => null];
        }

        if (is_string($target)) {
            return ['type' => $target, 'id' => self::nullableString($id)];
        }

        if ($target instanceof Model) {
            return ['type' => $target::class, 'id' => self::nullableString($target->getKey())];
        }

        return ['type' => $target::class, 'id' => self::nullableString($target->getAuthIdentifier())];
    }

    /**
     * @return array{type: string|null, id: string|null}
     */
    public static function subject(Model | string | null $target, string | int | null $id = null): array
    {
        if ($target === null) {
            return ['type' => null, 'id' => null];
        }

        if (is_string($target)) {
            return ['type' => $target, 'id' => self::nullableString($id)];
        }

        return ['type' => $target::class, 'id' => self::nullableString($target->getKey())];
    }

    private static function nullableString(mixed $id): ?string
    {
        return $id === null ? null : (string) $id;
    }
}
