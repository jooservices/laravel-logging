<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;

final class DefaultLogContextResolver implements LogContextResolverInterface
{
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

        $headerRequestId = $request->headers->get('X-Request-Id');
        $headerCorrelationId = $request->headers->get('X-Correlation-Id');
        $requestId = $headerRequestId === null || $headerRequestId === '' ? (string) Str::uuid() : $headerRequestId;
        $correlationId = $headerCorrelationId === null || $headerCorrelationId === '' ? $requestId : $headerCorrelationId;

        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'trace_id' => $this->traceId($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'context' => [
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'route' => $request->route()?->getName(),
                ],
            ],
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

    private function traceId(Request $request): ?string
    {
        $traceparent = $request->headers->get('traceparent');

        if (! is_string($traceparent) || $traceparent === '') {
            return null;
        }

        $parts = explode('-', $traceparent);

        return $parts[1] ?? null;
    }
}
