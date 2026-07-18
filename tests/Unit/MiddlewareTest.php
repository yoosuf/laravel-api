<?php

namespace Yoosuf\LaravelApi\Tests\Unit;

use Illuminate\Http\Request;
use Yoosuf\LaravelApi\Http\Middleware\ApiVersionMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\DeprecationMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\ForceJsonMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\RequestIdMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\SecurityHeadersMiddleware;
use Yoosuf\LaravelApi\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    // -------------------------------------------------------------------------
    // ForceJsonMiddleware
    // -------------------------------------------------------------------------

    public function test_force_json_sets_accept_header(): void
    {
        $request = Request::create('/test', 'GET');
        $this->assertNotSame('application/json', $request->header('Accept'));

        $captured = null;
        (new ForceJsonMiddleware)->handle($request, function (Request $req) use (&$captured) {
            $captured = $req;

            return response()->json([]);
        });

        $this->assertSame('application/json', $captured->header('Accept'));
    }

    public function test_force_json_does_not_alter_existing_json_accept(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        (new ForceJsonMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertSame('application/json', $request->header('Accept'));
    }

    // -------------------------------------------------------------------------
    // RequestIdMiddleware
    // -------------------------------------------------------------------------

    public function test_request_id_middleware_adds_headers_when_not_present(): void
    {
        $request = Request::create('/test', 'GET');

        $response = (new RequestIdMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Correlation-ID'));
        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->headers->get('X-Correlation-ID')
        );
    }

    public function test_request_id_middleware_propagates_existing_id(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_X_REQUEST_ID' => 'my-trace-id-123']);

        $response = (new RequestIdMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertSame('my-trace-id-123', $response->headers->get('X-Request-ID'));
        $this->assertSame('my-trace-id-123', $response->headers->get('X-Correlation-ID'));
    }

    public function test_request_id_middleware_skipped_when_disabled(): void
    {
        config()->set('laravel-api.request_id.enabled', false);

        $request = Request::create('/test', 'GET');
        $response = (new RequestIdMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertNull($response->headers->get('X-Request-ID'));
    }

    public function test_request_id_stored_on_request_attributes(): void
    {
        $request = Request::create('/test', 'GET');

        (new RequestIdMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertNotEmpty($request->attributes->get('request_id'));
    }

    // -------------------------------------------------------------------------
    // SecurityHeadersMiddleware
    // -------------------------------------------------------------------------

    public function test_security_headers_are_added_to_response(): void
    {
        $request = Request::create('/test', 'GET');

        $response = (new SecurityHeadersMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('strict-origin', $response->headers->get('Referrer-Policy'));
    }

    // -------------------------------------------------------------------------
    // ApiVersionMiddleware
    // -------------------------------------------------------------------------

    public function test_versioning_disabled_by_default_passes_through(): void
    {
        config()->set('laravel-api.versioning.enabled', false);

        $request = Request::create('/test', 'GET');
        $response = (new ApiVersionMiddleware)->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_version_extracted_from_query_param(): void
    {
        config()->set('laravel-api.versioning.enabled', true);

        $request = Request::create('/test?api-version=1.0', 'GET');

        (new ApiVersionMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertSame('1.0', $request->attributes->get('api_version'));
    }

    public function test_version_extracted_from_header(): void
    {
        config()->set('laravel-api.versioning.enabled', true);

        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_API_VERSION' => '2.0']);

        (new ApiVersionMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertSame('2.0', $request->attributes->get('api_version'));
    }

    public function test_unsupported_version_returns_400(): void
    {
        config()->set('laravel-api.versioning.enabled', true);
        config()->set('laravel-api.versioning.supported', ['1.0', '2.0']);

        $request = Request::create('/test?api-version=9.9', 'GET');

        $response = (new ApiVersionMiddleware)->handle($request, fn () => response()->json([]));

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('UnsupportedApiVersion', $payload['error']['code']);
    }

    public function test_supported_version_passes_through(): void
    {
        config()->set('laravel-api.versioning.enabled', true);
        config()->set('laravel-api.versioning.supported', ['1.0', '2.0']);

        $request = Request::create('/test?api-version=1.0', 'GET');

        $response = (new ApiVersionMiddleware)->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1.0', $request->attributes->get('api_version'));
    }

    // -------------------------------------------------------------------------
    // DeprecationMiddleware
    // -------------------------------------------------------------------------

    public function test_deprecation_header_is_set(): void
    {
        $request = Request::create('/test', 'GET');

        $response = (new DeprecationMiddleware)->handle(
            $request,
            fn () => response()->json([]),
            '2025-01-01'
        );

        $this->assertNotEmpty($response->headers->get('Deprecation'));
    }

    public function test_sunset_header_is_set_when_provided(): void
    {
        $request = Request::create('/test', 'GET');

        $response = (new DeprecationMiddleware)->handle(
            $request,
            fn () => response()->json([]),
            '2025-01-01',
            '2026-01-01'
        );

        $this->assertNotEmpty($response->headers->get('Sunset'));
    }

    public function test_successor_url_sets_link_header(): void
    {
        $request = Request::create('/test', 'GET');

        $response = (new DeprecationMiddleware)->handle(
            $request,
            fn () => response()->json([]),
            'true',
            '',
            'https://api.example.com/v2/resource'
        );

        $link = $response->headers->get('Link', '');
        $this->assertStringContainsString('successor-version', $link);
    }

    public function test_deprecation_literal_true_is_preserved(): void
    {
        $request = Request::create('/test', 'GET');

        $response = (new DeprecationMiddleware)->handle(
            $request,
            fn () => response()->json([]),
            'true'
        );

        $this->assertSame('true', $response->headers->get('Deprecation'));
    }

    public function test_no_deprecation_header_when_date_is_empty(): void
    {
        $request = Request::create('/test', 'GET');

        $response = (new DeprecationMiddleware)->handle(
            $request,
            fn () => response()->json([]),
            ''
        );

        $this->assertNull($response->headers->get('Deprecation'));
    }
}
