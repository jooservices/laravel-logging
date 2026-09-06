<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Opt-in HTTP request logging via the system adapter.
 *
 * Register in the application middleware stack when
 * config('laravel-logging.http.enabled') is true.
 */
final class LogHttpRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('laravel-logging.http.enabled', false)) {
            return $next($request);
        }

        if ($this->shouldIgnore($request)) {
            return $next($request);
        }

        $started = microtime(true);
        $response = $next($request);

        try {
            $builder = ActivityLog::system()
                ->action((string) config('laravel-logging.http.action', 'http.request'))
                ->level((string) config('laravel-logging.http.level', 'info'))
                ->withRequest($request)
                ->properties([
                    'method' => $request->method(),
                    'path' => '/' . ltrim($request->path(), '/'),
                    'status' => $response->getStatusCode(),
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ]);

            if ((bool) config('laravel-logging.http.queue', false)) {
                $builder->queue()->dispatch();

                return $response;
            }

            $builder->save();
        } catch (Throwable) {
            // Never break the HTTP response path because logging failed.
        }

        return $response;
    }

    private function shouldIgnore(Request $request): bool
    {
        /** @var array<int, string> $patterns */
        $patterns = config('laravel-logging.http.ignore_paths', []);
        $path = ltrim($request->path(), '/');

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
