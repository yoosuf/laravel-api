<?php

namespace Yoosuf\LaravelApi\Tests\Integration;

use Illuminate\Support\Facades\Route;
use Yoosuf\LaravelApi\Tests\TestCase;

/**
 * End-to-end tests that route real HTTP requests through middleware and verify
 * the headers and behaviour at the response level.
 */
class MiddlewareIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laravel-api.request_id.enabled', true);
        $app['config']->set('laravel-api.openapi.docs_route.enabled', false);
        $app['config']->set('laravel-api.health.enabled', false);
    }

    // -------------------------------------------------------------------------
    // ForceJsonMiddleware
    // -------------------------------------------------------------------------

    public function test_force_json_middleware_ensures_json_response_on_error(): void
    {
        Route::middleware(['laravel-api.force-json'])->get('/test-force-json', fn () => response()->json(['ok' => true]));

        $response = $this->get('/test-force-json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    // -------------------------------------------------------------------------
    // RequestIdMiddleware
    // -------------------------------------------------------------------------

    public function test_request_id_middleware_adds_headers_to_response(): void
    {
        Route::middleware(['laravel-api.request-id'])->get('/test-rid', fn () => response()->json([]));

        $response = $this->get('/test-rid');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Correlation-ID'));
        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->headers->get('X-Correlation-ID')
        );
    }

    public function test_request_id_middleware_propagates_client_request_id(): void
    {
        Route::middleware(['laravel-api.request-id'])->get('/test-rid-prop', fn () => response()->json([]));

        $response = $this->withHeaders(['X-Request-ID' => 'client-trace-abc'])->get('/test-rid-prop');

        $this->assertSame('client-trace-abc', $response->headers->get('X-Request-ID'));
    }

    // -------------------------------------------------------------------------
    // SecurityHeadersMiddleware
    // -------------------------------------------------------------------------

    public function test_security_headers_middleware_adds_expected_headers(): void
    {
        Route::middleware(['laravel-api.security-headers'])->get('/test-sec', fn () => response()->json([]));

        $response = $this->get('/test-sec');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    // -------------------------------------------------------------------------
    // ApiVersionMiddleware
    // -------------------------------------------------------------------------

    public function test_versioning_middleware_rejects_unsupported_version(): void
    {
        config()->set('laravel-api.versioning.enabled', true);
        config()->set('laravel-api.versioning.supported', ['1.0', '2.0']);

        Route::middleware(['laravel-api.versioning'])->get('/test-ver', fn () => response()->json([]));

        $response = $this->getJson('/test-ver?api-version=9.9');

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'UnsupportedApiVersion');
    }

    public function test_versioning_middleware_accepts_supported_version(): void
    {
        config()->set('laravel-api.versioning.enabled', true);
        config()->set('laravel-api.versioning.supported', ['1.0', '2.0']);

        Route::middleware(['laravel-api.versioning'])->get('/test-ver-ok', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/test-ver-ok?api-version=2.0');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // DeprecationMiddleware
    // -------------------------------------------------------------------------

    public function test_deprecation_middleware_adds_deprecation_header(): void
    {
        Route::middleware(['laravel-api.deprecation:2025-01-01,2026-01-01'])->get('/test-dep', fn () => response()->json([]));

        $response = $this->get('/test-dep');

        $this->assertNotEmpty($response->headers->get('Deprecation'));
        $this->assertNotEmpty($response->headers->get('Sunset'));
    }
}
