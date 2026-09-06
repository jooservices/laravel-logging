<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

/**
 * Filters model attribute bags before they enter audit changes.
 */
final class AuditableAttributes
{
    /**
     * @return list<string>
     */
    public static function defaultExcept(): array
    {
        return [
            'password',
            'password_confirmation',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'current_password',
            'secret',
            'token',
            'api_token',
            'access_token',
            'refresh_token',
            'accessToken',
            'apiKey',
            'authorization',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>|null  $only
     * @param  array<int, string>  $except
     * @param  array<int, string>  $hidden
     * @return array<string, mixed>
     */
    public static function filter(array $attributes, ?array $only, array $except, array $hidden): array
    {
        $except = array_values(array_unique([...self::defaultExcept(), ...$except]));
        $filtered = [];

        foreach ($attributes as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }

            // Skip Eloquent $hidden unless the consumer allowlisted the field via only().
            if ($only === null && in_array($key, $hidden, true)) {
                continue;
            }

            if (in_array($key, $except, true)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
