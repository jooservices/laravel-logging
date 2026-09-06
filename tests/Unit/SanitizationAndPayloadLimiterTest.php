<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Services\ActivityLogPayloadLimiter;
use JOOservices\LaravelLogging\Services\DefaultLogSanitizer;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SanitizationAndPayloadLimiterTest extends TestCase
{
    public function test_sanitizer_redacts_exact_case_insensitive_nested_keys(): void
    {
        $faker = \Faker\Factory::create();
        $password = $faker->password();
        $token = $faker->sha256();
        $name = $faker->firstName();

        $sanitizer = new DefaultLogSanitizer(
            keys: ['password', 'token'],
            replacement: '[redacted]',
        );

        $payload = $sanitizer->sanitize([
            'Password' => $password,
            'profile' => ['token' => $token, 'name' => $name],
        ]);

        $this->assertSame('[redacted]', $payload['Password']);
        $this->assertSame('[redacted]', $payload['profile']['token']);
        $this->assertSame($name, $payload['profile']['name']);
    }

    public function test_sanitizer_suffix_match_redacts_csrf_token_not_unrelated_keys(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: ['token'],
            replacement: '[redacted]',
        );

        $payload = $sanitizer->sanitize([
            'csrf_token' => 'x',
            'notation' => 'keep',
            'notoken' => 'x',
        ]);

        // Suffix/compact denylist: keys ending in "token" redact; "notation" must not.
        $this->assertSame('[redacted]', $payload['csrf_token']);
        $this->assertSame('[redacted]', $payload['notoken']);
        $this->assertSame('keep', $payload['notation']);
    }

    public function test_sanitizer_preserves_list_integer_keys(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: ['token'],
            replacement: '[redacted]',
        );

        $payload = $sanitizer->sanitize([
            'items' => ['one', 'two', 'three'],
        ]);

        $this->assertIsArray($payload['items']);
        /** @var array<array-key, mixed> $items */
        $items = $payload['items'];
        $this->assertSame([0, 1, 2], array_keys($items));
        $this->assertSame(['one', 'two', 'three'], $items);
    }

    public function test_sanitizer_redacts_camel_case_and_value_patterns(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: ['access_token', 'api_key'],
            replacement: '[redacted]',
            valuePatterns: [
                '/(?i)^Bearer\s+/',
                '/(?i)^eyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/',
            ],
        );

        $object = new stdClass();
        $object->nested = 'Bearer abc.def';

        $payload = $sanitizer->sanitize([
            'accessToken' => 'leak',
            'apiKey' => 'leak',
            'safe' => 'ok',
            'auth' => 'Bearer xyz',
            'jwt' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.sig',
            'wrapped' => $object,
        ]);

        $this->assertSame('[redacted]', $payload['accessToken']);
        $this->assertSame('[redacted]', $payload['apiKey']);
        $this->assertSame('ok', $payload['safe']);
        $this->assertSame('[redacted]', $payload['auth']);
        $this->assertSame('[redacted]', $payload['jwt']);
        $this->assertSame('[redacted]', $payload['wrapped']['nested']);
    }

    public function test_sanitizer_ignores_invalid_patterns(): void
    {
        $sanitizer = new DefaultLogSanitizer(
            keys: [],
            replacement: '[redacted]',
            patterns: ['/[invalid'],
            valuePatterns: ['/[invalid'],
        );

        $this->assertSame(['token' => 'visible'], $sanitizer->sanitize(['token' => 'visible']));
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

    public function test_payload_limiter_preserves_structural_top_level_strings(): void
    {
        $limiter = new ActivityLogPayloadLimiter([
            'enabled' => true,
            'max_string_length' => 5,
            'max_array_items' => 200,
            'max_depth' => 8,
            'max_document_bytes' => 10000,
            'truncate_marker' => '[truncated]',
        ]);

        $payload = $limiter->limit([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'action' => 'payload.guarded',
            'occurred_at' => '2026-01-01T00:00:00+00:00',
            'message' => 'abcdefghij',
            'properties' => ['description' => 'abcdefghij'],
        ]);

        $this->assertSame('11111111-1111-1111-1111-111111111111', $payload['uuid']);
        $this->assertSame('payload.guarded', $payload['action']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $payload['occurred_at']);
        $this->assertSame('abcde[truncated]', $payload['message']);
        $this->assertSame('abcde[truncated]', $payload['properties']['description']);
    }

    public function test_payload_limiter_preserves_batch_id_when_truncating_items(): void
    {
        $limiter = new ActivityLogPayloadLimiter([
            'enabled' => true,
            'max_string_length' => 100,
            'max_array_items' => 2,
            'max_depth' => 5,
            'max_document_bytes' => 10000,
            'truncate_marker' => '[truncated]',
        ]);

        $payload = $limiter->limit([
            'a' => 1,
            'b' => 2,
            'batch_id' => 'keep-me',
            'c' => 3,
        ]);

        $this->assertSame('keep-me', $payload['batch_id']);
        $this->assertArrayHasKey('__truncated_items', $payload);
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

    public function test_document_size_limit_fails_closed_on_invalid_json(): void
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
            'properties' => ['bad' => NAN],
        ]);

        $this->assertSame(['__truncated_document' => '[truncated]'], $payload);
    }
}
