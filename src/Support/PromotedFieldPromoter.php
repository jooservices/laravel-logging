<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

final class PromotedFieldPromoter
{
    /**
     * Top-level fields that must never be overwritten by promotion.
     *
     * @var list<string>
     */
    private const RESERVED = [
        'uuid',
        'type',
        'adapter',
        'level',
        'action',
        'message',
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'source',
        'source_type',
        'request_id',
        'correlation_id',
        'trace_id',
        'ip_address',
        'user_agent',
        'tenant_id',
        'properties',
        'context',
        'changes',
        'occurred_at',
        'created_at',
        'updated_at',
        '_id',
    ];

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function apply(array $document): array
    {
        /** @var array<string, string> $mappings */
        $mappings = (array) config('laravel-logging.promoted_fields', []);

        foreach ($mappings as $targetField => $sourcePath) {
            if (! is_string($targetField) || ! is_string($sourcePath) || $sourcePath === '') {
                continue;
            }

            if (in_array($targetField, self::RESERVED, true)) {
                continue;
            }

            $value = self::valueAtPath($document, $sourcePath);

            if ($value !== null) {
                $document[$targetField] = $value;
            }
        }

        return $document;
    }

    /**
     * @return list<array{keys: array<string, int>, options: array<string, mixed>}>
     */
    public static function indexDefinitions(): array
    {
        /** @var array<string, string> $mappings */
        $mappings = (array) config('laravel-logging.promoted_fields', []);
        $indexes = [];

        foreach (array_keys($mappings) as $field) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            $indexes[] = [
                'keys' => [$field => 1],
                'options' => [],
            ];
            $indexes[] = [
                'keys' => [$field => 1, 'occurred_at' => -1],
                'options' => [],
            ];
        }

        return $indexes;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private static function valueAtPath(array $document, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $document;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
