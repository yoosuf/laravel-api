<?php

namespace Yoosuf\LaravelApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signals that a route or API version is deprecated and/or will be removed.
 *
 * Sets standard HTTP deprecation headers on the response:
 *   Deprecation: <RFC 7231 HTTP-date or "true">
 *   Sunset:      <RFC 7231 HTTP-date> (optional)
 *   Link:        <url>; rel="successor-version" (optional)
 *
 * Usage in route definitions:
 *
 *   Route::middleware(['laravel-api.deprecation:2025-01-01,2026-01-01'])->group(...)
 *
 * Both dates must be RFC 7231 HTTP-date strings (e.g. "Sat, 01 Jan 2025 00:00:00 GMT")
 * or ISO 8601 dates that will be formatted automatically. Pass an empty string to omit.
 */
class DeprecationMiddleware
{
    public function handle(Request $request, Closure $next, string $deprecatedSince = 'true', string $sunset = '', string $successorUrl = ''): Response
    {
        $response = $next($request);

        if ($deprecatedSince !== '') {
            $response->headers->set('Deprecation', $this->normaliseDate($deprecatedSince));
        }

        if ($sunset !== '') {
            $response->headers->set('Sunset', $this->normaliseDate($sunset));
        }

        if ($successorUrl !== '') {
            $response->headers->set('Link', "<{$successorUrl}>; rel=\"successor-version\"");
        }

        return $response;
    }

    private function normaliseDate(string $value): string
    {
        if ($value === 'true' || str_contains($value, ',')) {
            return $value; // already formatted or literal 'true'
        }

        try {
            $dt = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));

            return $dt->format('D, d M Y H:i:s \G\M\T');
        } catch (\Throwable) {
            return $value;
        }
    }
}
