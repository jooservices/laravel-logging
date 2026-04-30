<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Services;

use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;

final class DefaultLogSanitizer implements LogSanitizerInterface
{
    /**
     * @param  array<int, string>  $keys
     */
    public function __construct(
        private readonly array $keys,
        private readonly string $replacement,
    ) {}

    public function sanitize(array $payload): array
    {
        return $this->sanitizeArray($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $payload): array
    {
        $sensitive = array_map('strtolower', $this->keys);

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
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
}
