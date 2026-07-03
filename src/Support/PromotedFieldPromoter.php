<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

final class PromotedFieldPromoter
{
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
