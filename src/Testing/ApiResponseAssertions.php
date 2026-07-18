<?php

namespace Yoosuf\LaravelApi\Testing;

use Illuminate\Testing\TestResponse;

/**
 * Fluent API response assertions for Laravel test cases.
 *
 * Add this trait to your test case to get expressive helpers for asserting
 * the standard envelope and structured error formats produced by ApiResponder.
 *
 * Usage:
 *
 *   class MyTest extends TestCase {
 *       use ApiResponseAssertions;
 *   }
 */
trait ApiResponseAssertions
{
    public function assertApiSuccess(TestResponse $response, int $status = 200): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'type', 'message', 'data']);

        return $response;
    }

    public function assertApiCreated(TestResponse $response): TestResponse
    {
        return $this->assertApiSuccess($response, 201);
    }

    public function assertApiAccepted(TestResponse $response): TestResponse
    {
        return $this->assertApiSuccess($response, 202);
    }

    public function assertApiNoContent(TestResponse $response): TestResponse
    {
        $response->assertStatus(204);

        return $response;
    }

    public function assertApiPaginated(TestResponse $response, int $status = 200): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonStructure(['value', '@count']);

        return $response;
    }

    public function assertApiError(TestResponse $response, int $status): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonPath('ok', false);

        return $response;
    }

    public function assertApiValidationError(TestResponse $response, ?string $field = null): TestResponse
    {
        $response->assertStatus(422)
            ->assertJsonPath('ok', false);

        if ($field !== null) {
            $response->assertJsonPath("errors.{$field}", fn ($val) => $val !== null);
        }

        return $response;
    }

    public function assertApiUnauthorized(TestResponse $response): TestResponse
    {
        return $this->assertApiError($response, 401);
    }

    public function assertApiForbidden(TestResponse $response): TestResponse
    {
        return $this->assertApiError($response, 403);
    }

    public function assertApiNotFound(TestResponse $response): TestResponse
    {
        return $this->assertApiError($response, 404);
    }

    public function assertApiTooManyRequests(TestResponse $response, ?int $retryAfter = null): TestResponse
    {
        $this->assertApiError($response, 429);

        if ($retryAfter !== null) {
            $response->assertHeader('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    public function assertStructuredError(TestResponse $response, string $code, int $status): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonPath('error.code', $code)
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);

        return $response;
    }

    public function assertApiDataKey(TestResponse $response, string $key, mixed $value): TestResponse
    {
        $response->assertJsonPath("data.{$key}", $value);

        return $response;
    }

    public function assertApiMeta(TestResponse $response, string $key, mixed $value): TestResponse
    {
        $response->assertJsonPath("meta.{$key}", $value);

        return $response;
    }

    public function assertApiHasRequestId(TestResponse $response): TestResponse
    {
        $response->assertHeader('X-Request-ID');

        return $response;
    }
}
