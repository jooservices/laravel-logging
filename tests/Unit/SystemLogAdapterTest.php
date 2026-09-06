<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Adapters\SystemLogAdapter;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Tests\TestCase;
use RuntimeException;

final class SystemLogAdapterTest extends TestCase
{
    public function test_command_failed_truncates_long_exception_message_and_includes_debug_location(): void
    {
        config()->set('app.debug', true);

        $adapter = new SystemLogAdapter(
            $this->app->make(LogStoreInterface::class),
            $this->app->make(LogSanitizerInterface::class),
            $this->app->make(ActivityLogPayloadLimiterInterface::class),
            $this->app->make(LogContextResolverInterface::class),
        );

        $failed = $adapter
            ->commandFailed('demo:run', new RuntimeException(str_repeat('x', 520)))
            ->toData();

        $this->assertSame('command.failed', $failed->action);
        $this->assertSame('error', $failed->level);
        $this->assertIsArray($failed->context['exception']);
        $exception = $failed->context['exception'];
        $this->assertIsString($exception['message']);
        $this->assertStringEndsWith('[truncated]', $exception['message']);
        $this->assertArrayHasKey('file', $exception);
        $this->assertArrayHasKey('line', $exception);
    }
}
