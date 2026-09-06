<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\DTO\LogActorData;
use JOOservices\LaravelLogging\DTO\LogSubjectData;

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

    public static function actorData(Model | Authenticatable | string | null $target, string | int | null $id = null): LogActorData
    {
        return LogActorData::from(self::actor($target, $id));
    }

    public static function subjectData(Model | string | null $target, string | int | null $id = null): LogSubjectData
    {
        return LogSubjectData::from(self::subject($target, $id));
    }

    public static function externalActor(string $type, string | int | null $id = null): LogActorData
    {
        return new LogActorData($type, self::nullableString($id));
    }

    public static function externalSubject(string $type, string | int | null $id = null): LogSubjectData
    {
        return new LogSubjectData($type, self::nullableString($id));
    }

    private static function nullableString(mixed $id): ?string
    {
        return $id === null ? null : (string) $id;
    }
}
