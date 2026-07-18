<?php

namespace Yoosuf\LaravelApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security response headers to every API response.
 *
 * Headers applied:
 *   X-Content-Type-Options: nosniff         — prevents MIME-type sniffing
 *   X-Frame-Options: DENY                   — prevents clickjacking via frames
 *   Cache-Control: no-store, no-cache       — prevents sensitive data caching
 *   Referrer-Policy: strict-origin          — limits referrer leakage
 *
 * Can be registered as laravel-api.security-headers on specific route groups
 * or globally on the api middleware stack.
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Referrer-Policy', 'strict-origin');

        return $response;
    }
}
