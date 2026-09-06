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
            'HTTP_TRACEPARENT' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00',
            'HTTP_USER_AGENT' => 'ResolverTest',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $resolved = (new DefaultLogContextResolver($this->app))->resolve($request);

        $this->assertNotEmpty($resolved['request_id']);
        $this->assertSame($resolved['request_id'], $resolved['correlation_id']);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $resolved['trace_id']);
        $this->assertSame('10.0.0.1', $resolved['ip_address']);
        $this->assertSame('GET', $resolved['context']['request']['method']);
        $this->assertSame('http://localhost/demo', $resolved['context']['request']['url']);
    }

    public function test_resolve_request_url_excludes_query_string(): void
    {
        $request = Request::create('/callback?code=secret-token&state=xyz', 'GET');

        $resolved = (new DefaultLogContextResolver($this->app))->resolve($request);

        $this->assertSame('http://localhost/callback', $resolved['context']['request']['url']);
        $this->assertStringNotContainsString('secret-token', (string) $resolved['context']['request']['url']);
    }

    public function test_resolve_stores_inbound_headers_as_external_ids_only(): void
    {
        $request = Request::create('/demo', 'POST', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'req-42',
            'HTTP_X_CORRELATION_ID' => 'corr-99',
        ]);

        $resolved = (new DefaultLogContextResolver($this->app))->resolve($request);

        $this->assertNotSame('req-42', $resolved['request_id']);
        $this->assertNotSame('corr-99', $resolved['correlation_id']);
        $this->assertSame($resolved['request_id'], $resolved['correlation_id']);
        $this->assertSame('req-42', $resolved['context']['request']['external_request_id']);
        $this->assertSame('corr-99', $resolved['context']['request']['external_correlation_id']);
        $this->assertNull($resolved['trace_id']);
    }

    public function test_resolve_reuses_request_scoped_ids_and_rejects_poisoned_headers(): void
    {
        $request = Request::create('/demo', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => str_repeat('a', 200),
            'HTTP_TRACEPARENT' => 'not-a-traceparent',
        ]);
        $resolver = new DefaultLogContextResolver($this->app);

        $first = $resolver->resolve($request);
        $second = $resolver->resolve($request);

        $this->assertSame($first['request_id'], $second['request_id']);
        $this->assertSame($first['correlation_id'], $second['correlation_id']);
        $requestContext = $first['context']['request'] ?? [];
        $this->assertIsArray($requestContext);
        $this->assertArrayNotHasKey('external_request_id', $requestContext);
        $this->assertNull($first['trace_id']);
    }

    public function test_resolve_truncates_long_user_agent_and_rejects_invalid_external_ids(): void
    {
        $request = Request::create('/demo', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => str_repeat('U', 600),
            'HTTP_X_REQUEST_ID' => 'bad id with spaces',
        ]);

        $resolved = (new DefaultLogContextResolver($this->app))->resolve($request);

        $this->assertSame(512, mb_strlen((string) $resolved['user_agent']));
        $requestContext = $resolved['context']['request'] ?? [];
        $this->assertIsArray($requestContext);
        $this->assertArrayNotHasKey('external_request_id', $requestContext);
    }
}
