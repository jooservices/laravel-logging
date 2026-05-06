<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Services;

use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;

final class ActivityLogPayloadLimiter implements ActivityLogPayloadLimiterInterface
{
    /**
     * @param  array{
     *     enabled?: bool,
     *     max_string_length?: int,
     *     max_array_items?: int,
     *     max_depth?: int,
     *     max_document_bytes?: int,
     *     truncate_marker?: string
     * }  $config
     */
    public function __construct(private readonly array $config) {}

    public function limit(array $payload): array
    {
        if ($this->enabled() === false) {
            return $payload;
        }

        $limited = $this->limitValue($payload, 0);

        if (! is_array($limited)) {
            return [];
        }

        return $this->limitDocumentSize($limited);
    }

    private function limitValue(mixed $value, int $depth): mixed
    {
        if (is_string($value)) {
            return $this->limitString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        if ($depth >= $this->maxDepth()) {
            return $this->marker();
        }

        $limited = [];
        $count = 0;

        foreach ($value as $key => $item) {
            if ($count >= $this->maxArrayItems()) {
                $limited['__truncated_items'] = $this->marker();

                break;
            }

            $limited[$key] = $this->limitValue($item, $depth + 1);
            $count++;
        }

        return $limited;
    }

    private function limitString(string $value): string
    {
        $maxLength = $this->maxStringLength();

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        if (in_array($value, ['[redacted]', '[REDACTED]'], true)) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength).$this->marker();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function limitDocumentSize(array $payload): array
    {
        $encoded = json_encode($payload);

        if ($encoded === false || strlen($encoded) <= $this->maxDocumentBytes()) {
            return $payload;
        }

        foreach (['properties', 'context', 'changes'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->marker();
            }

            $encoded = json_encode($payload);

            if ($encoded !== false && strlen($encoded) <= $this->maxDocumentBytes()) {
                return $payload;
            }
        }

        return ['__truncated_document' => $this->marker()];
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    private function maxStringLength(): int
    {
        return max(1, (int) ($this->config['max_string_length'] ?? 5000));
    }

    private function maxArrayItems(): int
    {
        return max(1, (int) ($this->config['max_array_items'] ?? 200));
    }

    private function maxDepth(): int
    {
        return max(1, (int) ($this->config['max_depth'] ?? 8));
    }

    private function maxDocumentBytes(): int
    {
        return max(1, (int) ($this->config['max_document_bytes'] ?? 524288));
    }

    private function marker(): string
    {
        return (string) ($this->config['truncate_marker'] ?? '[truncated]');
    }
}
