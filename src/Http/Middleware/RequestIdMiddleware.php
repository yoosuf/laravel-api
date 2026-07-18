<?php

namespace Yoosuf\LaravelApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches a unique request correlation ID to every request and response.
 *
 * If the incoming request already carries the configured header the existing
 * value is propagated; otherwise a UUID v4 is generated. Both the primary
 * header (X-Request-ID) and the correlation alias (X-Correlation-ID) are
 * written on the response, fulfilling RFC 7240 / correlation ID conventions.
 */
class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('laravel-api.request_id.enabled', true)) {
            return $next($request);
        }

        $header = (string) config('laravel-api.request_id.header', 'X-Request-ID');
        $correlationHeader = (string) config('laravel-api.request_id.correlation_header', 'X-Correlation-ID');

        $requestId = $request->header($header) ?? (string) Str::uuid();

        $request->headers->set($header, $requestId);
        $request->headers->set($correlationHeader, $requestId);
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set($header, $requestId);
        $response->headers->set($correlationHeader, $requestId);

        return $response;
    }
}
