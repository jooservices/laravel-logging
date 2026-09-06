<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Services;

use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;

final class ActivityLogPayloadLimiter implements ActivityLogPayloadLimiterInterface
{
    /**
     * Prefer keeping these keys when max_array_items truncates associative bags.
     *
     * @var list<string>
     */
    private const PRIORITY_KEYS = ['batch_id', 'workflow_id', 'tenant_id', 'request_id', 'correlation_id'];

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
    public function __construct(private readonly array $config)
    {
    }

    public function limit(array $payload): array
    {
        if ($this->enabled() === false) {
            return $payload;
        }

        $limited = $this->limitValue($payload, 0);

        if (! is_array($limited)) {
            return ['__truncated_document' => $this->marker()];
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

        $ordered = $this->prioritizeKeys($value);
        $limited = [];
        $count = 0;

        foreach ($ordered as $key => $item) {
            if ($count >= $this->maxArrayItems()) {
                $limited['__truncated_items'] = $this->marker();

                break;
            }

            $limited[$key] = $this->limitValue($item, $depth + 1);
            $count++;
        }

        return $limited;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function prioritizeKeys(array $value): array
    {
        if ($value === [] || array_is_list($value)) {
            return $value;
        }

        $priority = [];
        $rest = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, self::PRIORITY_KEYS, true)) {
                $priority[$key] = $item;

                continue;
            }

            $rest[$key] = $item;
        }

        return $priority + $rest;
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

        return mb_substr($value, 0, $maxLength) . $this->marker();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function limitDocumentSize(array $payload): array
    {
        $encoded = json_encode($payload);

        // Fail closed: invalid UTF-8 / INF / NAN must not bypass the budget.
        if ($encoded === false) {
            return ['__truncated_document' => $this->marker()];
        }

        if (strlen($encoded) <= $this->maxDocumentBytes()) {
            return $payload;
        }

        foreach (['properties', 'context', 'changes', 'message', 'user_agent', 'exception'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->marker();
            }

            $encoded = json_encode($payload);

            if ($encoded === false) {
                return ['__truncated_document' => $this->marker()];
            }

            if (strlen($encoded) <= $this->maxDocumentBytes()) {
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
