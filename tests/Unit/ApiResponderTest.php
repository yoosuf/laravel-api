<?php

namespace Yoosuf\LaravelApi\Tests\Unit;

use Yoosuf\LaravelApi\Http\ApiResponder;
use Yoosuf\LaravelApi\Tests\TestCase;

class ApiResponderTest extends TestCase
{
    public function test_success_response_shape_and_status(): void
    {
        $response = $this->app->make(ApiResponder::class)->responseSuccess([
            'id' => 1,
        ], 'OK', 200);

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['ok']);
        $this->assertSame('success', $payload['type']);
        $this->assertSame('OK', $payload['message']);
        $this->assertSame(1, $payload['data']['id']);
    }

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

    public function test_configurable_envelope_keys_are_respected(): void
    {
        config()->set('laravel-api.response.envelope.ok_key', 'success');
        config()->set('laravel-api.response.envelope.message_key', 'msg');

        $response = $this->app->make(ApiResponder::class)->success(null, 'Configured');
        $payload = $response->getData(true);

        $this->assertArrayHasKey('success', $payload);
        $this->assertArrayHasKey('msg', $payload);
        $this->assertTrue($payload['success']);
        $this->assertSame('Configured', $payload['msg']);
    }

    public function test_no_content_returns_empty_204_response(): void
    {
        $response = $this->app->make(ApiResponder::class)->noContent();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNotFalse($response->getContent());
    }
}
