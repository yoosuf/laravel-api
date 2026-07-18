<?php

namespace Yoosuf\LaravelApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads and validates the API version supplied by the caller.
 *
 * Version may be provided via the configured query parameter (default:
 * `api-version`) or request header (default: `Api-Version`). When a list of
 * supported versions is configured and the requested version is not in that
 * list, a structured-format 400 error is returned immediately per
 * REST API guidelines §2.
 *
 * The resolved version string is stored on `$request->attributes` under the
 * key `api_version` so downstream controllers and middleware can read it
 * without re-parsing headers.
 */
class ApiVersionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('laravel-api.versioning.enabled', false)) {
            return $next($request);
        }

        $queryParam = (string) config('laravel-api.versioning.query_param', 'api-version');
        $header = (string) config('laravel-api.versioning.header', 'Api-Version');
        $supportedVersions = (array) config('laravel-api.versioning.supported', []);

        $version = $request->query($queryParam) ?? $request->header($header);

        if (is_string($version) && $version !== '' && $supportedVersions !== [] && ! in_array($version, $supportedVersions, true)) {
            return response()->json([
                'error' => [
                    'code' => 'UnsupportedApiVersion',
                    'message' => sprintf(
                        "The API version '%s' is not supported. Supported versions: %s.",
                        $version,
                        implode(', ', $supportedVersions)
                    ),
                    'target' => $queryParam,
                    'details' => [],
                    'innererror' => null,
                ],
            ], 400);
        }

        if (is_string($version) && $version !== '') {
            $request->attributes->set('api_version', $version);
        }

        return $next($request);
    }
}
