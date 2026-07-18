<?php

namespace Yoosuf\LaravelApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces every incoming request to declare JSON as its accepted content type.
 *
 * Without this, Laravel's exception handler renders HTML error pages for
 * unhandled exceptions even on API routes. Registering this middleware on the
 * api middleware group ensures all responses — including framework-level errors
 * such as 404, 405, and authentication failures — are returned as JSON.
 */
class ForceJsonMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
