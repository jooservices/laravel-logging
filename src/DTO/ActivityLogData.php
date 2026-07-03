<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\DTO;

use DateTimeInterface;
use JOOservices\Dto\Core\Dto;

final class ActivityLogData extends Dto
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly string $adapter,
        public readonly ?string $level,
        public readonly string $action,
        public readonly ?string $message,
        public readonly ?string $actorType,
        public readonly ?string $actorId,
        public readonly ?string $subjectType,
        public readonly ?string $subjectId,
        public readonly ?string $causerType,
        public readonly ?string $causerId,
        public readonly ?string $source,
        public readonly ?string $sourceType,
        public readonly ?string $requestId,
        public readonly ?string $correlationId,
        public readonly ?string $traceId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $tenantId,
        public readonly array $properties,
        public readonly array $context,
        public readonly array $changes,
        public readonly DateTimeInterface|string|null $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPersistenceArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'adapter' => $this->adapter,
            'level' => $this->level,
            'action' => $this->action,
            'message' => $this->message,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'causer_type' => $this->causerType,
            'causer_id' => $this->causerId,
            'source' => $this->source,
            'source_type' => $this->sourceType,
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'trace_id' => $this->traceId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'tenant_id' => $this->tenantId,
            'properties' => $this->properties,
            'context' => $this->context,
            'changes' => $this->changes,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
