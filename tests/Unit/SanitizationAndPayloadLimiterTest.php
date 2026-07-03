<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Services\ActivityLogPayloadLimiter;
use JOOservices\LaravelLogging\Services\DefaultLogSanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizationAndPayloadLimiterTest extends TestCase
{
    public function test_sanitizer_redacts_exact_case_insensitive_nested_keys(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: ['password', 'token'],
            replacement: '[redacted]',
        );

        $payload = $sanitizer->sanitize([
            'Password' => 'secret',
            'profile' => ['token' => 'abc', 'name' => 'Taylor'],
        ]);

        $this->assertSame('[redacted]', $payload['Password']);
        $this->assertSame('[redacted]', $payload['profile']['token']);
        $this->assertSame('Taylor', $payload['profile']['name']);
    }

    public function test_sanitizer_can_be_disabled(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: ['password'],
            replacement: '[redacted]',
            enabled: false,
        );

        $this->assertSame(['password' => 'secret'], $sanitizer->sanitize(['password' => 'secret']));
    }

    public function test_sanitizer_matches_sensitive_patterns_and_case_sensitive_keys(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: ['Token'],
            replacement: '[redacted]',
            enabled: true,
            caseSensitive: true,
            patterns: ['/secret_/'],
        );

        $payload = $sanitizer->sanitize([
            'Token' => 'abc',
            'token' => 'visible',
            'secret_value' => 'hidden',
        ]);

        $this->assertSame('[redacted]', $payload['Token']);
        $this->assertSame('visible', $payload['token']);
        $this->assertSame('[redacted]', $payload['secret_value']);
    }

    public function test_payload_limiter_truncates_strings_lists_and_depth(): void
    {
        $limiter = new ActivityLogPayloadLimiter([
            'enabled' => true,
            'max_string_length' => 5,
            'max_array_items' => 10,
            'max_depth' => 2,
            'max_document_bytes' => 10000,
            'truncate_marker' => '[truncated]',
        ]);

        $payload = $limiter->limit([
            'long' => 'abcdefghij',
            'list' => array_fill(0, 11, 'item'),
            'nested' => ['a' => ['b' => ['c' => true]]],
            'count' => 10,
        ]);

        $this->assertSame('abcde[truncated]', $payload['long']);
        $this->assertSame('[truncated]', $payload['list']['__truncated_items']);
        $this->assertSame('[truncated]', $payload['nested']['a']);
        $this->assertSame(10, $payload['count']);
    }

    public function test_payload_limiter_can_be_disabled(): void
    {
        $limiter = new ActivityLogPayloadLimiter(['enabled' => false]);
        $payload = ['long' => str_repeat('a', 10)];

        $this->assertSame($payload, $limiter->limit($payload));
    }

    public function test_document_size_limit_is_deterministic(): void
    {
        $limiter = new ActivityLogPayloadLimiter([
            'enabled' => true,
            'max_string_length' => 1000,
            'max_array_items' => 20,
            'max_depth' => 5,
            'max_document_bytes' => 80,
            'truncate_marker' => '[truncated]',
        ]);

        $payload = $limiter->limit([
            'properties' => ['body' => str_repeat('a', 100)],
            'context' => ['id' => 'ctx'],
            'changes' => ['id' => 'chg'],
        ]);

        $this->assertSame('[truncated]', $payload['properties']);
    }
}
