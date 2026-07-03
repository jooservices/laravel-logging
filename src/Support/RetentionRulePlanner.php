<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

use Carbon\CarbonImmutable;

final class RetentionRulePlanner
{
    /**
     * @return list<array{
     *     adapter: string|null,
     *     level: string|null,
     *     action_prefix: string|null,
     *     cutoff: CarbonImmutable
     * }>
     */
    public static function rules(): array
    {
        /** @var list<array<string, mixed>> $configured */
        $configured = (array) config('laravel-logging.retention.rules', []);
        $plans = [];

        foreach ($configured as $rule) {
            $parsed = self::parseRule($rule);

            if ($parsed !== null) {
                $plans[] = $parsed;
            }
        }

        return $plans;
    }

    /**
     * @return array{
     *     adapter: string|null,
     *     level: string|null,
     *     action_prefix: string|null,
     *     cutoff: CarbonImmutable
     * }|null
     */
    private static function parseRule(mixed $rule): ?array
    {
        if (! is_array($rule)) {
            return null;
        }

        $days = filter_var($rule['retention_days'] ?? null, FILTER_VALIDATE_INT);

        if ($days === false || $days < 1) {
            return null;
        }

        return [
            'adapter' => self::optionalString($rule['adapter'] ?? null),
            'level' => self::optionalString($rule['level'] ?? null),
            'action_prefix' => self::optionalString($rule['action_prefix'] ?? null),
            'cutoff' => CarbonImmutable::now('UTC')->subDays($days),
        ];
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
