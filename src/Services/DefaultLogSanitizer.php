<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Services;

use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;

final class DefaultLogSanitizer implements LogSanitizerInterface
{
    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $patterns
     */
    public function __construct(
        private readonly array $keys,
        private readonly string $replacement,
        private readonly bool $enabled = true,
        private readonly bool $caseSensitive = false,
        private readonly array $patterns = [],
    ) {
    }

    public function sanitize(array $payload): array
    {
        if ($this->enabled === false) {
            return $payload;
        }

        return $this->sanitizeArray($payload);
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

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $payload[$key] = $this->sanitizeArray($value);
            }
        }

        return $payload;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach ($this->keys as $sensitive) {
            if ($this->caseSensitive ? $key === $sensitive : strtolower($key) === strtolower($sensitive)) {
                return true;
            }
        }

        foreach ($this->patterns as $pattern) {
            set_error_handler(static fn(): bool => true);
            $matched = preg_match($pattern, $key) === 1;
            restore_error_handler();

            if ($matched) {
                return true;
            }
        }

        return false;
    }
}
