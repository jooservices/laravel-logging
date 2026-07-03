<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Illuminate\Http\Request;
use JOOservices\LaravelLogging\Services\DefaultLogContextResolver;
use JOOservices\LaravelLogging\Tests\TestCase;

final class DefaultLogContextResolverTest extends TestCase
{
    public function test_resolve_returns_empty_context_without_request(): void
    {
        $this->app->forgetInstance('request');

        $resolver = new DefaultLogContextResolver($this->app);

        $this->assertSame([
            'request_id' => null,
            'correlation_id' => null,
            'trace_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'context' => [],
        ], $resolver->resolve());
    }

    public function test_resolve_generates_request_id_and_reads_traceparent(): void
    {
        $request = Request::create('/demo', 'GET', [], [], [], [
            'HTTP_TRACEPARENT' => '00-abc123def456-00f067aa0ba902b7-00',
            'HTTP_USER_AGENT' => 'ResolverTest',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $resolved = (new DefaultLogContextResolver($this->app))->resolve($request);

        $this->assertNotEmpty($resolved['request_id']);
        $this->assertSame($resolved['request_id'], $resolved['correlation_id']);
        $this->assertSame('abc123def456', $resolved['trace_id']);
        $this->assertSame('10.0.0.1', $resolved['ip_address']);
        $this->assertSame('GET', $resolved['context']['request']['method']);
    }

    public function test_resolve_uses_explicit_request_and_correlation_headers(): void
    {
        $request = Request::create('/demo', 'POST', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'req-42',
            'HTTP_X_CORRELATION_ID' => 'corr-99',
        ]);

        $resolved = (new DefaultLogContextResolver($this->app))->resolve($request);

        $this->assertSame('req-42', $resolved['request_id']);
        $this->assertSame('corr-99', $resolved['correlation_id']);
        $this->assertNull($resolved['trace_id']);
    }
}
