<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Services;

use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JsonSerializable;
use Throwable;
use Traversable;

final class DefaultLogSanitizer implements LogSanitizerInterface
{
    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $patterns
     * @param  array<int, string>  $valuePatterns
     */
    public function __construct(
        private readonly array $keys,
        private readonly string $replacement,
        private readonly bool $enabled = true,
        private readonly bool $caseSensitive = false,
        private readonly array $patterns = [],
        private readonly array $valuePatterns = [],
    ) {
    }

    public function sanitize(array $payload): array
    {
        if ($this->enabled === false) {
            return $payload;
        }

        return $this->sanitizeArray($this->stringKeyed($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $payload[$key] = $this->replacement;

                continue;
            }

            $payload[$key] = $this->sanitizeValue($value);
        }

        return $payload;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($this->stringKeyed($value));
        }

        if (is_object($value)) {
            if ($value instanceof JsonSerializable) {
                $serialized = $value->jsonSerialize();

                return is_array($serialized)
                    ? $this->sanitizeArray($this->stringKeyed($serialized))
                    : $this->sanitizeValue($serialized);
            }

            if ($value instanceof Traversable) {
                return $this->sanitizeArray($this->stringKeyed(iterator_to_array($value)));
            }

            return $this->sanitizeArray($this->stringKeyed(get_object_vars($value)));
        }

        if (is_string($value) && $this->matchesValuePattern($value)) {
            return $this->replacement;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    private function stringKeyed(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $item) {
            $result[is_string($key) ? $key : (string) $key] = $item;
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach ($this->keys as $sensitive) {
            if ($this->keyMatches($key, $sensitive)) {
                return true;
            }
        }

        foreach ($this->patterns as $pattern) {
            if ($this->safePregMatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    private function keyMatches(string $key, string $sensitive): bool
    {
        if ($this->caseSensitive) {
            if ($key === $sensitive) {
                return true;
            }

            return str_ends_with($key, $sensitive)
                || str_ends_with($key, ucfirst($sensitive));
        }

        $left = strtolower($key);
        $right = strtolower($sensitive);

        if ($left === $right) {
            return true;
        }

        $compactLeft = str_replace(['_', '-'], '', $left);
        $compactRight = str_replace(['_', '-'], '', $right);

        return $compactLeft === $compactRight
            || str_ends_with($compactLeft, $compactRight);
    }

    private function matchesValuePattern(string $value): bool
    {
        foreach ($this->valuePatterns as $pattern) {
            if ($this->safePregMatch($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function safePregMatch(string $pattern, string $subject): bool
    {
        try {
            set_error_handler(static fn(): bool => true);
            $result = preg_match($pattern, $subject);
            restore_error_handler();

            return $result === 1;
        } catch (Throwable) {
            restore_error_handler();

            return false;
        }
    }
}
