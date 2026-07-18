<?php

namespace Yoosuf\LaravelApi\Tests\Integration;

use Illuminate\Support\Facades\Route;
use Yoosuf\LaravelApi\Tests\Fixtures\TestHasApiResponsesController;
use Yoosuf\LaravelApi\Tests\TestCase;

/**
 * End-to-end tests for HasApiResponses trait exercised through real HTTP routes.
 * Verifies the full controller → ApiResponder → JSON response chain.
 */
class HasApiResponsesIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::get('/ok', [TestHasApiResponsesController::class, 'ok']);
            Route::get('/created', [TestHasApiResponsesController::class, 'created']);
            Route::get('/accepted', [TestHasApiResponsesController::class, 'accepted']);
            Route::get('/no-content', [TestHasApiResponsesController::class, 'noContent']);
            Route::get('/paginated', [TestHasApiResponsesController::class, 'paginatedItems']);
            Route::get('/not-found', [TestHasApiResponsesController::class, 'notFoundError']);
            Route::get('/validation', [TestHasApiResponsesController::class, 'validationError']);
            Route::get('/rate-limit', [TestHasApiResponsesController::class, 'rateLimitError']);
            Route::get('/etag', [TestHasApiResponsesController::class, 'etagResponse']);
        });
    }

    // -------------------------------------------------------------------------
    // Success responses
    // -------------------------------------------------------------------------

    public function test_success_response_shape(): void
    {
        $response = $this->getJson('/api/ok');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('message', 'OK')
            ->assertJsonPath('data.id', 1);
    }

    public function test_created_response_sets_location_header(): void
    {
        $response = $this->getJson('/api/created');

        $response->assertStatus(201)
            ->assertJsonPath('ok', true);

        $this->assertSame('/api/users/2', $response->headers->get('Location'));
    }

    public function test_accepted_response_sets_location_header(): void
    {
        $response = $this->getJson('/api/accepted');

        $response->assertStatus(202);
        $this->assertSame('/api/jobs/99', $response->headers->get('Location'));
    }

    public function test_no_content_response_is_204(): void
    {
        $response = $this->getJson('/api/no-content');

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
    }

    // -------------------------------------------------------------------------
    // Paginated response
    // -------------------------------------------------------------------------

    public function test_paginated_response_shape(): void
    {
        $response = $this->getJson('/api/paginated');

        $response->assertStatus(200)
            ->assertJsonPath('@count', 50)
            ->assertJsonStructure(['value', '@count', '@nextLink', '@prevLink']);

        $this->assertCount(2, $response->json('value'));
    }

    public function test_paginated_response_sets_link_header(): void
    {
        $response = $this->getJson('/api/paginated');

        $link = $response->headers->get('Link', '');
        $this->assertStringContainsString('rel="next"', $link);
        $this->assertStringContainsString('rel="prev"', $link);
    }

    // -------------------------------------------------------------------------
    // Error responses
    // -------------------------------------------------------------------------

    public function test_not_found_response_is_404(): void
    {
        $response = $this->getJson('/api/not-found');

        $response->assertStatus(404)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'User not found');
    }

    public function test_validation_error_response_is_422_with_errors(): void
    {
        $response = $this->getJson('/api/validation');

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'type', 'message', 'errors']);

        $this->assertArrayHasKey('email', $response->json('errors'));
    }

    public function test_rate_limit_response_is_429_with_all_headers(): void
    {
        $response = $this->getJson('/api/rate-limit');

        $response->assertStatus(429);
        $this->assertSame('60', $response->headers->get('Retry-After'));
        $this->assertSame('100', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('1753000000', $response->headers->get('X-RateLimit-Reset'));
    }

    // -------------------------------------------------------------------------
    // ETag
    // -------------------------------------------------------------------------

    public function test_etag_header_is_present(): void
    {
        $response = $this->getJson('/api/etag');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get('ETag'));
        $this->assertStringStartsWith('W/"', $response->headers->get('ETag', ''));
    }
}
