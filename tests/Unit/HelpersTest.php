<?php

namespace Yoosuf\LaravelApi\Tests\Unit;

use Illuminate\Http\JsonResponse;
use Yoosuf\LaravelApi\Http\ApiResponder;
use Yoosuf\LaravelApi\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_api_response_returns_api_responder_instance(): void
    {
        $this->assertInstanceOf(ApiResponder::class, api_response());
    }

    public function test_api_response_returns_same_singleton(): void
    {
        $this->assertSame(api_response(), api_response());
    }

    public function test_response_success_returns_json_response(): void
    {
        $response = response_success(['id' => 1], 'OK', 200);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['ok']);
        $this->assertSame('OK', $payload['message']);
        $this->assertSame(1, $payload['data']['id']);
    }

    public function test_response_success_defaults_to_200(): void
    {
        $response = response_success();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_response_failed_returns_error_shape(): void
    {
        $response = response_failed('Bad input', 422, ['email' => ['Required.']]);

        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertSame('failed', $payload['type']);
        $this->assertArrayHasKey('email', $payload['errors']);
    }

    public function test_response_error_returns_server_error_shape(): void
    {
        $response = response_error('Internal error', 500);

        $this->assertSame(500, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertSame('error', $payload['type']);
    }

    public function test_api_paginated_returns_collection_shape(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $response = api_paginated($items, 100, 'http://localhost/api/items?page=2');

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame($items, $payload['value']);
        $this->assertSame(100, $payload['@count']);
        $this->assertSame('http://localhost/api/items?page=2', $payload['@nextLink']);
    }

    public function test_api_paginated_omits_count_when_null(): void
    {
        $response = api_paginated([], null);
        $payload = $response->getData(true);

        $this->assertArrayNotHasKey('@count', $payload);
    }

    public function test_api_paginated_sets_link_header(): void
    {
        $response = api_paginated([], 50, 'http://localhost/api/items?page=2');

        $this->assertStringContainsString('rel="next"', $response->headers->get('Link', ''));
    }
}
