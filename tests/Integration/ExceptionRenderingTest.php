<?php

namespace Yoosuf\LaravelApi\Tests\Integration;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Yoosuf\LaravelApi\Exceptions\ApiExceptionRenderer;
use Yoosuf\LaravelApi\Tests\TestCase;

/**
 * End-to-end tests for ApiExceptionRenderer that fire real HTTP requests
 * through Laravel's full exception handling pipeline.
 */
class ExceptionRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register the renderer for all tests in this class.
        $this->app->make(ApiExceptionRenderer::class)->register();

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::get('/throw-auth', fn () => throw new AuthenticationException('Unauthenticated.'));
            Route::get('/throw-authz', fn () => throw new AuthorizationException('Forbidden.'));
            Route::get('/throw-validation', function (): never {
                throw ValidationException::withMessages([
                    'email' => ['The email field is required.'],
                ]);
            });
            Route::get('/throw-model', function (): never {
                /** @var class-string $modelClass */
                $modelClass = 'App\Models\Order';
                throw (new ModelNotFoundException)->setModel($modelClass);
            });
            Route::get('/throw-404', fn () => throw new NotFoundHttpException('Not found.'));
            Route::get('/throw-409', fn () => throw new HttpException(409, 'Conflict.'));
            Route::get('/throw-500', fn () => throw new \RuntimeException('Server error.'));
        });
    }

    public function test_authentication_exception_renders_as_401_json(): void
    {
        $response = $this->getJson('/api/throw-auth');

        $response->assertStatus(401);
        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('type', 'failed');
    }

    public function test_authorization_exception_renders_as_403_json(): void
    {
        $response = $this->getJson('/api/throw-authz');

        $response->assertStatus(403);
        $response->assertJsonPath('ok', false);
    }

    public function test_validation_exception_renders_as_422_with_error_bag(): void
    {
        $response = $this->getJson('/api/throw-validation');

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $response->assertJsonStructure(['ok', 'type', 'message', 'errors']);
        $this->assertArrayHasKey('email', $response->json('errors'));
    }

    public function test_model_not_found_renders_as_404_json(): void
    {
        $response = $this->getJson('/api/throw-model');

        $response->assertStatus(404);
        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('Order', $response->json('message'));
    }

    public function test_404_http_exception_renders_as_404_json(): void
    {
        $response = $this->getJson('/api/throw-404');

        $response->assertStatus(404);
        $response->assertJsonPath('ok', false);
    }

    public function test_409_http_exception_renders_as_409_json(): void
    {
        $response = $this->getJson('/api/throw-409');

        $response->assertStatus(409);
        $response->assertJsonPath('ok', false);
    }

    public function test_runtime_exception_renders_as_500_json(): void
    {
        config()->set('app.debug', false);

        $response = $this->getJson('/api/throw-500');

        $response->assertStatus(500);
        $response->assertJsonPath('ok', false);
    }

    public function test_runtime_exception_hides_details_in_production(): void
    {
        config()->set('app.debug', false);

        $response = $this->getJson('/api/throw-500');

        $this->assertStringNotContainsString('Server error.', $response->json('message'));
    }

    public function test_exception_renderer_uses_structured_format_when_configured(): void
    {
        config()->set('app.debug', false);
        config()->set('laravel-api.response.error_format', 'structured');

        $response = $this->getJson('/api/throw-500');

        $response->assertStatus(500);
        $response->assertJsonPath('error.code', 'InternalServerError');
        $response->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }
}
