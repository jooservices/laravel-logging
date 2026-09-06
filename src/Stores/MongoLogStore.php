<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Stores;

use Illuminate\Support\Collection;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JOOservices\LaravelLogging\Repositories\ActivityLogRepository;

final class MongoLogStore implements LogStoreInterface
{
    public function __construct(
        private readonly ActivityLogRepository $repository,
        private readonly LogSanitizerInterface $sanitizer,
        private readonly ActivityLogPayloadLimiterInterface $payloadLimiter,
    ) {
    }

    public function record(ActivityLogData $data): ActivityLogRecord
    {
        return $this->repository->record($this->prepare($data));
    }

    /**
     * @param  list<ActivityLogData>  $records
     * @return Collection<int, ActivityLogRecord>
     */
    public function recordMany(array $records): Collection
    {
        $prepared = [];

        foreach ($records as $data) {
            $prepared[] = $this->prepare($data);
        }

        return $this->repository->recordMany($prepared);
    }

    /**
     * Single choke point: sanitize + limit for every write path (including queue dispatch).
     */
    public function prepare(ActivityLogData $data): ActivityLogData
    {
        $document = $data->toArray();

        $document['properties'] = $this->payloadLimiter->limit(
            $this->sanitizer->sanitize($this->arrayField($document['properties'] ?? [])),
        );
        $document['context'] = $this->payloadLimiter->limit(
            $this->sanitizer->sanitize($this->arrayField($document['context'] ?? [])),
        );
        $document['changes'] = $this->payloadLimiter->limit(
            $this->sanitizer->sanitize($this->arrayField($document['changes'] ?? [])),
        );

        $document['message'] = $this->clampScalar($document['message'] ?? null);
        $document['user_agent'] = $this->clampScalar($document['user_agent'] ?? null, 512);
        $document['request_id'] = $this->clampScalar($document['request_id'] ?? null, 128);
        $document['correlation_id'] = $this->clampScalar($document['correlation_id'] ?? null, 128);
        $document['trace_id'] = $this->clampScalar($document['trace_id'] ?? null, 64);
        $document['ip_address'] = $this->clampScalar($document['ip_address'] ?? null, 64);

        $document = $this->enforceDocumentBudget($document);
        $document['properties'] = $this->ensureAssocArray($document['properties'] ?? []);
        $document['context'] = $this->ensureAssocArray($document['context'] ?? []);
        $document['changes'] = $this->ensureAssocArray($document['changes'] ?? []);

        return ActivityLogData::from($document);
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureAssocArray(mixed $value): array
    {
        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        if (is_string($value)) {
            return ['__truncated' => $value];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function enforceDocumentBudget(array $document): array
    {
        $limited = $this->payloadLimiter->limit($document);

        if (array_keys($limited) === ['__truncated_document'] || ! isset($limited['uuid'], $limited['action'])) {
            $marker = is_string($limited['__truncated_document'] ?? null)
                ? $limited['__truncated_document']
                : '[truncated]';

            $document['properties'] = ['__truncated' => $marker];
            $document['context'] = ['__truncated' => $marker];
            $document['changes'] = ['__truncated' => $marker];

            if (is_string($document['message'] ?? null)) {
                $document['message'] = mb_substr($document['message'], 0, 200);
            }

            return $document;
        }

        return $this->stringKeyedDocument($limited);
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @return array<string, mixed>
     */
    private function stringKeyedDocument(array $document): array
    {
        $result = [];

        foreach ($document as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function clampScalar(mixed $value, ?int $max = null): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $limited = $this->payloadLimiter->limit(['v' => $value]);
        $clamped = $limited['v'] ?? $value;

        if (! is_string($clamped)) {
            return null;
        }

        if ($max !== null && mb_strlen($clamped) > $max) {
            return mb_substr($clamped, 0, $max);
        }

        return $clamped;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayField(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
