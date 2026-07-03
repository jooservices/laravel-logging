<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\DTO\LogActorData;
use JOOservices\LaravelLogging\DTO\LogSubjectData;
use JOOservices\LaravelLogging\Exceptions\InvalidLogDataException;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use JsonSerializable;
use Throwable;

abstract class BaseLogAdapter implements LogAdapterInterface
{
    protected string $type;

    protected string $adapter;

    protected ?string $level = null;

    protected ?string $action = null;

    protected ?string $message = null;

    protected ?LogActorData $actor = null;

    protected ?LogSubjectData $subject = null;

    protected ?LogActorData $causer = null;

    protected ?string $source = null;

    protected ?string $sourceType = null;

    protected ?string $requestId = null;

    protected ?string $correlationId = null;

    protected ?string $traceId = null;

    protected ?string $ipAddress = null;

    protected ?string $userAgent = null;

    protected ?string $tenantId = null;

    /** @var array<string, mixed> */
    protected array $properties = [];

    /** @var array<string, mixed> */
    protected array $context = [];

    /** @var array<string, mixed> */
    protected array $changes = [];

    protected DateTimeInterface|string|null $occurredAt = null;

    protected ?string $queueName = null;

    protected bool $syncDispatch = false;

    public function __construct(
        protected readonly LogStoreInterface $store,
        protected readonly LogSanitizerInterface $sanitizer,
        protected readonly ActivityLogPayloadLimiterInterface $payloadLimiter,
        protected readonly LogContextResolverInterface $contextResolver,
    ) {}

    public function type(string|BackedEnum $type): static
    {
        $this->type = $this->enumValue($type);

        return $this;
    }

    public function level(string|BackedEnum $level): static
    {
        $this->level = $this->enumValue($level);

        return $this;
    }

    public function action(string|BackedEnum $action): static
    {
        $this->action = $this->enumValue($action);

        return $this;
    }

    public function message(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function by(Model|Authenticatable|string|null $actor): static
    {
        $this->actor = LogActorData::fromTarget($actor);

        return $this;
    }

    public function byExternal(string $type, string|int|null $id = null): static
    {
        $this->actor = LogActorData::external($type, $id);

        return $this;
    }

    public function bySystem(): static
    {
        return $this->by('system');
    }

    public function byGuest(): static
    {
        return $this->by('guest');
    }

    public function on(Model|string|null $subject): static
    {
        $this->subject = LogSubjectData::fromTarget($subject);

        return $this;
    }

    public function onExternal(string $type, string|int|null $id = null): static
    {
        $this->subject = LogSubjectData::external($type, $id);

        return $this;
    }

    public function causedBy(Model|Authenticatable|string|null $causer): static
    {
        $this->causer = LogActorData::fromTarget($causer);

        return $this;
    }

    public function causedByExternal(string $type, string|int|null $id = null): static
    {
        $this->causer = LogActorData::external($type, $id);

        return $this;
    }

    public function source(string|BackedEnum|null $source): static
    {
        $this->source = $source === null ? null : $this->enumValue($source);

        return $this;
    }

    public function sourceType(string|BackedEnum|null $sourceType): static
    {
        $this->sourceType = $sourceType === null ? null : $this->enumValue($sourceType);

        return $this;
    }

    public function properties(array|Arrayable|JsonSerializable $properties): static
    {
        $this->properties = array_replace_recursive($this->properties, $this->payloadToArray($properties));

        return $this;
    }

    public function context(array|Arrayable|JsonSerializable $context): static
    {
        $this->context = array_replace_recursive($this->context, $this->payloadToArray($context));

        return $this;
    }

    public function withRequest(?Request $request = null): static
    {
        $resolved = $this->contextResolver->resolve($request);

        $this->requestId = $resolved['request_id'];
        $this->correlationId = $resolved['correlation_id'];
        $this->traceId = $resolved['trace_id'];
        $this->ipAddress = $resolved['ip_address'];
        $this->userAgent = $resolved['user_agent'];
        $this->context($resolved['context']);

        return $this;
    }

    public function correlationId(?string $correlationId): static
    {
        $this->correlationId = $correlationId;

        return $this;
    }

    public function requestId(?string $requestId): static
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function traceId(?string $traceId): static
    {
        $this->traceId = $traceId;

        return $this;
    }

    public function tenantId(string|int|null $tenantId): static
    {
        $this->tenantId = $tenantId === null ? null : (string) $tenantId;

        return $this;
    }

    public function batchId(string|int $batchId): static
    {
        $this->context['batch_id'] = (string) $batchId;

        return $this;
    }

    public function workflowId(string|int $workflowId): static
    {
        $this->context['workflow_id'] = (string) $workflowId;

        return $this;
    }

    public function occurredAt(DateTimeInterface|string|null $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function sync(): static
    {
        $this->syncDispatch = true;
        $this->queueName = null;

        return $this;
    }

    public function queue(?string $queue = null): static
    {
        $this->syncDispatch = false;
        $this->queueName = $queue;

        return $this;
    }

    public function toData(): ActivityLogData
    {
        if ($this->action === null || $this->action === '') {
            throw new InvalidLogDataException('Activity log action is required before saving.');
        }

        $properties = $this->payloadLimiter->limit($this->sanitizer->sanitize($this->properties));
        $context = $this->payloadLimiter->limit($this->sanitizer->sanitize($this->context));
        $changes = $this->payloadLimiter->limit($this->sanitizer->sanitize($this->changes));

        return new ActivityLogData(
            uuid: (string) Str::uuid(),
            type: $this->type,
            adapter: $this->adapter,
            level: $this->level,
            action: $this->action,
            message: $this->message,
            actorType: $this->actor?->type,
            actorId: $this->actor?->id,
            subjectType: $this->subject?->type,
            subjectId: $this->subject?->id,
            causerType: $this->causer?->type,
            causerId: $this->causer?->id,
            source: $this->source,
            sourceType: $this->sourceType,
            requestId: $this->requestId,
            correlationId: $this->correlationId,
            traceId: $this->traceId,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            tenantId: $this->tenantId,
            properties: $properties,
            context: $context,
            changes: $changes,
            occurredAt: $this->occurredAt ?? CarbonImmutable::now(),
        );
    }

    public function dispatch(): void
    {
        $data = $this->toData();

        if ($this->syncDispatch) {
            $this->store->record($data);

            return;
        }

        $dispatch = StoreActivityLogJob::dispatch($data);

        if ($this->queueName !== null) {
            $dispatch->onQueue($this->queueName);
        }
    }

    public function save(): ActivityLogRecord
    {
        return $this->store->record($this->toData());
    }

    protected function enumValue(string|BackedEnum $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>|JsonSerializable  $payload
     * @return array<string, mixed>
     */
    protected function payloadToArray(array|Arrayable|JsonSerializable $payload): array
    {
        if ($payload instanceof Arrayable) {
            /** @var array<string, mixed> */
            return $payload->toArray();
        }

        if ($payload instanceof JsonSerializable) {
            $serialized = $payload->jsonSerialize();

            return is_array($serialized) ? $serialized : ['value' => $serialized];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function setChanges(array $changes): void
    {
        $this->changes = $changes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function exceptionContext(Throwable $exception): array
    {
        return [
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
        ];
    }
}
