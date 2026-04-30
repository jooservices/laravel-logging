<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use Illuminate\Http\Request;

interface LogContextResolverInterface
{
    /**
     * @return array{
     *     request_id: string|null,
     *     correlation_id: string|null,
     *     trace_id: string|null,
     *     ip_address: string|null,
     *     user_agent: string|null,
     *     context: array<string, mixed>
     * }
     */
    public function resolve(?Request $request = null): array;
}
