<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\DTO;

use DateTimeInterface;
use JOOservices\Dto\Attributes\MapFrom;
use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Attributes\Validation\Required;
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
        #[Required]
        public readonly string $action,
        public readonly ?string $message,
        #[MapFrom('actor_type')]
        #[MapTo('actor_type')]
        public readonly ?string $actorType,
        #[MapFrom('actor_id')]
        #[MapTo('actor_id')]
        public readonly ?string $actorId,
        #[MapFrom('subject_type')]
        #[MapTo('subject_type')]
        public readonly ?string $subjectType,
        #[MapFrom('subject_id')]
        #[MapTo('subject_id')]
        public readonly ?string $subjectId,
        #[MapFrom('causer_type')]
        #[MapTo('causer_type')]
        public readonly ?string $causerType,
        #[MapFrom('causer_id')]
        #[MapTo('causer_id')]
        public readonly ?string $causerId,
        public readonly ?string $source,
        #[MapFrom('source_type')]
        #[MapTo('source_type')]
        public readonly ?string $sourceType,
        #[MapFrom('request_id')]
        #[MapTo('request_id')]
        public readonly ?string $requestId,
        #[MapFrom('correlation_id')]
        #[MapTo('correlation_id')]
        public readonly ?string $correlationId,
        #[MapFrom('trace_id')]
        #[MapTo('trace_id')]
        public readonly ?string $traceId,
        #[MapFrom('ip_address')]
        #[MapTo('ip_address')]
        public readonly ?string $ipAddress,
        #[MapFrom('user_agent')]
        #[MapTo('user_agent')]
        public readonly ?string $userAgent,
        #[MapFrom('tenant_id')]
        #[MapTo('tenant_id')]
        public readonly ?string $tenantId,
        public readonly array $properties,
        public readonly array $context,
        public readonly array $changes,
        #[MapFrom('occurred_at')]
        #[MapTo('occurred_at')]
        public readonly DateTimeInterface | string | null $occurredAt,
    ) {
    }
}
