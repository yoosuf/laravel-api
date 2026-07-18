<?php

namespace Yoosuf\LaravelApi\Tests\Unit;

use Yoosuf\LaravelApi\Http\ApiResponder;
use Yoosuf\LaravelApi\Tests\TestCase;

class ApiResponderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Success responses
    // -------------------------------------------------------------------------

    public function test_success_response_shape_and_status(): void
    {
        $response = $this->app->make(ApiResponder::class)->responseSuccess(['id' => 1], 'OK', 200);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['ok']);
        $this->assertSame('success', $payload['type']);
        $this->assertSame('OK', $payload['message']);
        $this->assertSame(1, $payload['data']['id']);
    }

    public function test_created_returns_201(): void
    {
        $response = $this->app->make(ApiResponder::class)->created(['id' => 5]);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['ok']);
        $this->assertSame(5, $payload['data']['id']);
    }

    public function test_accepted_returns_202(): void
    {
        $response = $this->app->make(ApiResponder::class)->accepted();

        $this->assertSame(202, $response->getStatusCode());
    }

    public function test_no_content_returns_empty_204_response(): void
    {
        $response = $this->app->make(ApiResponder::class)->noContent();

        $this->assertSame(204, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Paginated responses
    // -------------------------------------------------------------------------

    public function test_paginated_returns_odata_lite_shape(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $response = $this->app->make(ApiResponder::class)->paginated($items, 50, 'https://example.com/api/items?page=2');
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($items, $payload['value']);
        $this->assertSame(50, $payload['@count']);
        $this->assertSame('https://example.com/api/items?page=2', $payload['@nextLink']);
        $this->assertArrayNotHasKey('@prevLink', $payload);
        $this->assertStringContainsString('rel="next"', $response->headers->get('Link', ''));
    }

    public function test_paginated_omits_count_when_total_null(): void
    {
        $response = $this->app->make(ApiResponder::class)->paginated([], null);
        $payload = $response->getData(true);

        $this->assertArrayNotHasKey('@count', $payload);
        $this->assertArrayNotHasKey('@nextLink', $payload);
        $this->assertArrayNotHasKey('@prevLink', $payload);
    }

    public function test_paginated_sets_link_header_for_both_directions(): void
    {
        $response = $this->app->make(ApiResponder::class)->paginated(
            [],
            100,
            'https://api.test/items?page=3',
            'https://api.test/items?page=1'
        );

        $link = $response->headers->get('Link', '');
        $this->assertStringContainsString('rel="next"', $link);
        $this->assertStringContainsString('rel="prev"', $link);
    }

    // -------------------------------------------------------------------------
    // Location header
    // -------------------------------------------------------------------------

    public function test_created_sets_location_header(): void
    {
        $response = $this->app->make(ApiResponder::class)->created(['id' => 99], 'Created', null, '/api/orders/99');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/api/orders/99', $response->headers->get('Location'));
    }

    public function test_accepted_sets_location_header(): void
    {
        $response = $this->app->make(ApiResponder::class)->accepted(null, 'Accepted', null, '/api/jobs/42');

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('/api/jobs/42', $response->headers->get('Location'));
    }

    // -------------------------------------------------------------------------
    // Envelope error responses (default format)
    // -------------------------------------------------------------------------

    public function test_failed_and_error_aliases_are_available(): void
    {
        $responder = $this->app->make(ApiResponder::class);

        $failed = $responder->responseFailed('Bad input', 422, ['field' => ['required']]);
        $errorViaTypoAlias = $responder->responseErrror('Oops', 500, ['trace' => 'hidden']);
        $errorViaCorrectAlias = $responder->responseError('Oops2', 503, ['downstream' => true]);

        $failedPayload = $failed->getData(true);
        $errorPayload = $errorViaTypoAlias->getData(true);
        $errorPayload2 = $errorViaCorrectAlias->getData(true);

        $this->assertFalse($failedPayload['ok']);
        $this->assertSame('failed', $failedPayload['type']);
        $this->assertArrayHasKey('field', $failedPayload['errors']);

        $this->assertFalse($errorPayload['ok']);
        $this->assertSame('error', $errorPayload['type']);
        $this->assertSame(500, $errorViaTypoAlias->getStatusCode());

        $this->assertFalse($errorPayload2['ok']);
        $this->assertSame('error', $errorPayload2['type']);
        $this->assertSame(503, $errorViaCorrectAlias->getStatusCode());
    }

    public function test_named_4xx_shortcuts(): void
    {
        $responder = $this->app->make(ApiResponder::class);

        $this->assertSame(400, $responder->badRequest()->getStatusCode());
        $this->assertSame(401, $responder->unauthorized()->getStatusCode());
        $this->assertSame(403, $responder->forbidden()->getStatusCode());
        $this->assertSame(404, $responder->notFound()->getStatusCode());
        $this->assertSame(409, $responder->conflict()->getStatusCode());
        $this->assertSame(410, $responder->gone()->getStatusCode());
        $this->assertSame(422, $responder->validation()->getStatusCode());
        $this->assertSame(422, $responder->unprocessable()->getStatusCode());
        $this->assertSame(423, $responder->locked()->getStatusCode());
        $this->assertSame(429, $responder->tooManyRequests()->getStatusCode());
    }

    public function test_named_5xx_shortcuts(): void
    {
        $responder = $this->app->make(ApiResponder::class);

        $this->assertSame(501, $responder->notImplemented()->getStatusCode());
        $this->assertSame(503, $responder->serviceUnavailable()->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // X-RateLimit headers
    // -------------------------------------------------------------------------

    public function test_too_many_requests_sets_retry_after_header(): void
    {
        $response = $this->app->make(ApiResponder::class)->tooManyRequests('Slow down', null, null, 60);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('60', $response->headers->get('Retry-After'));
    }

    public function test_too_many_requests_sets_rate_limit_headers(): void
    {
        $response = $this->app->make(ApiResponder::class)
            ->tooManyRequests('Slow down', null, null, 60, 100, 0, 1753000000);

        $this->assertSame('100', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('1753000000', $response->headers->get('X-RateLimit-Reset'));
    }

    public function test_service_unavailable_sets_retry_after_header(): void
    {
        $response = $this->app->make(ApiResponder::class)->serviceUnavailable('Down for maintenance', null, null, 300);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('300', $response->headers->get('Retry-After'));
    }

    public function test_retry_after_omitted_when_null(): void
    {
        $response = $this->app->make(ApiResponder::class)->tooManyRequests();

        $this->assertNull($response->headers->get('Retry-After'));
    }

    // -------------------------------------------------------------------------
    // validation() — ValidationException integration
    // -------------------------------------------------------------------------

    public function test_validation_accepts_validation_exception(): void
    {
        $exception = \Illuminate\Validation\ValidationException::withMessages([
            'email' => ['The email is required.'],
        ]);

        $response = $this->app->make(ApiResponder::class)->validation($exception);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('email', $payload['errors']);
    }

    // -------------------------------------------------------------------------
    // ETag helpers
    // -------------------------------------------------------------------------

    public function test_with_etag_adds_etag_header(): void
    {
        $responder = $this->app->make(ApiResponder::class);
        $response = $responder->success(['id' => 1]);
        $response = $responder->withEtag($response);

        $this->assertNotEmpty($response->headers->get('ETag'));
        $this->assertStringStartsWith('W/"', $response->headers->get('ETag', ''));
    }

    public function test_not_modified_returns_304(): void
    {
        $response = $this->app->make(ApiResponder::class)->notModified();

        $this->assertSame(304, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // structured error format
    // -------------------------------------------------------------------------

    public function test_structured_error_format_for_failed(): void
    {
        config()->set('laravel-api.response.error_format', 'structured');

        $response = $this->app->make(ApiResponder::class)->failed(
            'One or more fields are invalid.',
            422,
            ['email' => ['The email field is required.'], 'name' => ['Too short.']]
        );

        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame('UnprocessableEntity', $payload['error']['code']);
        $this->assertSame('One or more fields are invalid.', $payload['error']['message']);
        $this->assertNotEmpty($payload['error']['details']);
        $this->assertSame('email', $payload['error']['details'][0]['target']);
        $this->assertArrayNotHasKey('ok', $payload);
    }

    public function test_structured_error_format_for_error(): void
    {
        config()->set('laravel-api.response.error_format', 'structured');

        $response = $this->app->make(ApiResponder::class)->error('Something went wrong.', 500);
        $payload = $response->getData(true);

        $this->assertSame('InternalServerError', $payload['error']['code']);
        $this->assertSame([], $payload['error']['details']);
    }

    public function test_explicit_structured_error_method(): void
    {
        $response = $this->app->make(ApiResponder::class)->structuredError(
            'ResourceNotFound',
            'The order was not found.',
            404,
            'orderId',
            [['code' => 'InvalidId', 'message' => 'Must be a positive integer.', 'target' => 'orderId']]
        );

        $payload = $response->getData(true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('ResourceNotFound', $payload['error']['code']);
        $this->assertSame('orderId', $payload['error']['target']);
        $this->assertCount(1, $payload['error']['details']);
    }

    public function test_envelope_format_unaffected_in_default_mode(): void
    {
        $response = $this->app->make(ApiResponder::class)->failed('Bad request', 400);
        $payload = $response->getData(true);

        $this->assertArrayHasKey('ok', $payload);
        $this->assertArrayNotHasKey('error', $payload);
    }

    // -------------------------------------------------------------------------
    // Lazy config — changes at runtime are reflected immediately
    // -------------------------------------------------------------------------

    public function test_configurable_envelope_keys_are_respected(): void
    {
        config()->set('laravel-api.response.envelope.ok_key', 'success');
        config()->set('laravel-api.response.envelope.message_key', 'msg');

        // Re-resolve to get a fresh instance that reads the updated config lazily.
        $response = $this->app->make(ApiResponder::class)->success(null, 'Configured');
        $payload = $response->getData(true);

        $this->assertArrayHasKey('success', $payload);
        $this->assertArrayHasKey('msg', $payload);
        $this->assertTrue($payload['success']);
        $this->assertSame('Configured', $payload['msg']);
    }

    public function test_lazy_config_reflects_runtime_changes(): void
    {
        // First call with default key
        $r1 = $this->app->make(ApiResponder::class)->success(['a' => 1]);
        $this->assertArrayHasKey('ok', $r1->getData(true));

        // Change config without re-binding
        config()->set('laravel-api.response.envelope.ok_key', 'status');
        $r2 = $this->app->make(ApiResponder::class)->success(['a' => 2]);
        $this->assertArrayHasKey('status', $r2->getData(true));
    }
}
