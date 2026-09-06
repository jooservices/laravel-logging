<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;

final class DefaultLogContextResolver implements LogContextResolverInterface
{
    private const ATTR_REQUEST_ID = 'laravel_logging.request_id';

    private const ATTR_CORRELATION_ID = 'laravel_logging.correlation_id';

    private const MAX_EXTERNAL_ID_LENGTH = 128;

    public function __construct(private readonly Application $app)
    {
    }

    public function resolve(?Request $request = null): array
    {
        $request ??= $this->currentRequest();

        if (! $request instanceof Request) {
            return [
                'request_id' => null,
                'correlation_id' => null,
                'trace_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'context' => [],
            ];
        }

        // Always mint internal IDs — inbound headers are untrusted for trail joining.
        $requestId = $this->requestScopedGeneratedId($request, self::ATTR_REQUEST_ID);
        $correlationId = $this->requestScopedGeneratedId($request, self::ATTR_CORRELATION_ID, $requestId);

        $userAgent = $request->userAgent();
        if (is_string($userAgent) && mb_strlen($userAgent) > 512) {
            $userAgent = mb_substr($userAgent, 0, 512);
        }

        $context = [
            'request' => [
                'method' => $request->method(),
                // Prefer url() over fullUrl() so query-string secrets
                // (signed URLs, OAuth codes) are not persisted by default.
                'url' => $request->url(),
                'route' => $request->route()?->getName(),
            ],
        ];

        $externalRequestId = $this->sanitizeExternalId($request->headers->get('X-Request-Id'));
        $externalCorrId = $this->sanitizeExternalId($request->headers->get('X-Correlation-Id'));

        if ($externalRequestId !== null) {
            $context['request']['external_request_id'] = $externalRequestId;
        }

        if ($externalCorrId !== null) {
            $context['request']['external_correlation_id'] = $externalCorrId;
        }

        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'trace_id' => $this->traceId($request),
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'context' => $context,
        ];
    }

    private function currentRequest(): ?Request
    {
        if (! $this->app->bound('request')) {
            return null;
        }

        $request = $this->app->make('request');

        return $request instanceof Request ? $request : null;
    }

    private function requestScopedGeneratedId(
        Request $request,
        string $attribute,
        ?string $fallback = null,
    ): string {
        $existing = $request->attributes->get($attribute);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = $fallback ?? (string) Str::uuid();
        $request->attributes->set($attribute, $id);

        return $id;
    }

    private function sanitizeExternalId(mixed $header): ?string
    {
        if (! is_string($header) || $header === '') {
            return null;
        }

        if (strlen($header) > self::MAX_EXTERNAL_ID_LENGTH) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9._:-]+$/', $header) !== 1) {
            return null;
        }

        return $header;
    }

    private function traceId(Request $request): ?string
    {
        $traceparent = $request->headers->get('traceparent');

        if (! is_string($traceparent) || $traceparent === '') {
            return null;
        }

        // W3C Trace Context: version-trace_id-parent_id-trace_flags
        $matched = preg_match(
            '/^([0-9a-f]{2})-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/i',
            $traceparent,
            $matches,
        );

        if ($matched !== 1) {
            return null;
        }

        return strtolower($matches[2]);
    }
}
