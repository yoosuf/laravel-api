<?php

namespace Yoosuf\LaravelApi\Tests\Unit;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Yoosuf\LaravelApi\Exceptions\ApiExceptionRenderer;
use Yoosuf\LaravelApi\Tests\TestCase;

class ApiExceptionRendererTest extends TestCase
{
    private ApiExceptionRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = $this->app->make(ApiExceptionRenderer::class);
    }

    private function jsonRequest(string $uri = '/api/test'): Request
    {
        $request = Request::create($uri, 'GET');
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    private function htmlRequest(): Request
    {
        return Request::create('/test', 'GET');
    }

    // -------------------------------------------------------------------------
    // shouldRenderAsJson gating
    // -------------------------------------------------------------------------

    public function test_returns_null_for_non_json_non_api_request(): void
    {
        $result = $this->renderer->render(new \RuntimeException('oops'), $this->htmlRequest());

        $this->assertNull($result);
    }

    public function test_renders_for_api_path_without_accept_header(): void
    {
        $request = Request::create('/api/orders', 'GET');
        $result = $this->renderer->render(new \RuntimeException('oops'), $request);

        $this->assertNotNull($result);
    }

    // -------------------------------------------------------------------------
    // AuthenticationException → 401
    // -------------------------------------------------------------------------

    public function test_authentication_exception_returns_401(): void
    {
        $response = $this->renderer->render(new AuthenticationException, $this->jsonRequest());

        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
    }

    // -------------------------------------------------------------------------
    // AuthorizationException → 403
    // -------------------------------------------------------------------------

    public function test_authorization_exception_returns_403(): void
    {
        $response = $this->renderer->render(new AuthorizationException, $this->jsonRequest());

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // ValidationException → 422
    // -------------------------------------------------------------------------

    public function test_validation_exception_returns_422_with_errors(): void
    {
        $exception = ValidationException::withMessages([
            'email' => ['The email field is required.'],
            'name' => ['The name field is required.'],
        ]);

        $response = $this->renderer->render($exception, $this->jsonRequest());

        $this->assertNotNull($response);
        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertArrayHasKey('email', $payload['errors']);
        $this->assertArrayHasKey('name', $payload['errors']);
    }

    // -------------------------------------------------------------------------
    // ModelNotFoundException → 404
    // -------------------------------------------------------------------------

    public function test_model_not_found_returns_404(): void
    {
        /** @var class-string $modelClass */
        $modelClass = 'App\Models\Order';
        $exception = (new ModelNotFoundException)->setModel($modelClass);

        $response = $this->renderer->render($exception, $this->jsonRequest());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertStringContainsString('Order', $payload['message']);
    }

    // -------------------------------------------------------------------------
    // HttpException → matching status
    // -------------------------------------------------------------------------

    public function test_http_exception_401_returns_401(): void
    {
        $response = $this->renderer->render(new HttpException(401, 'Token expired'), $this->jsonRequest());

        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_http_exception_403_returns_403(): void
    {
        $response = $this->renderer->render(new HttpException(403, 'Access denied'), $this->jsonRequest());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_http_exception_404_returns_404(): void
    {
        $response = $this->renderer->render(new HttpException(404), $this->jsonRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_http_exception_405_returns_405(): void
    {
        $response = $this->renderer->render(new HttpException(405, 'Method not allowed'), $this->jsonRequest());

        $this->assertSame(405, $response->getStatusCode());
    }

    public function test_http_exception_429_returns_429(): void
    {
        $response = $this->renderer->render(
            new HttpException(429, 'Too many requests', null, ['Retry-After' => '30']),
            $this->jsonRequest()
        );

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    public function test_http_exception_500_returns_500(): void
    {
        $response = $this->renderer->render(new HttpException(500, 'Server fault'), $this->jsonRequest());

        $this->assertSame(500, $response->getStatusCode());
    }

    public function test_arbitrary_4xx_http_exception_returns_correct_status(): void
    {
        $response = $this->renderer->render(new HttpException(409, 'Conflict'), $this->jsonRequest());

        $this->assertSame(409, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Generic Throwable → 500
    // -------------------------------------------------------------------------

    public function test_generic_exception_returns_500(): void
    {
        $response = $this->renderer->render(new \RuntimeException('Something broke'), $this->jsonRequest());

        $this->assertNotNull($response);
        $this->assertSame(500, $response->getStatusCode());
    }

    public function test_generic_exception_hides_message_in_production(): void
    {
        config()->set('app.debug', false);

        $response = $this->renderer->render(new \RuntimeException('secret detail'), $this->jsonRequest());

        $this->assertNotNull($response);
        $payload = $response->getData(true);
        $this->assertStringNotContainsString('secret detail', $payload['message']);
    }

    public function test_generic_exception_shows_message_in_debug_mode(): void
    {
        config()->set('app.debug', true);

        $response = $this->renderer->render(new \RuntimeException('debug detail'), $this->jsonRequest());

        $this->assertNotNull($response);
        $payload = $response->getData(true);
        $this->assertStringContainsString('debug detail', $payload['message']);
    }

    // -------------------------------------------------------------------------
    // Structured error format passthrough
    // -------------------------------------------------------------------------

    public function test_generic_exception_uses_structured_format_when_configured(): void
    {
        config()->set('app.debug', false);
        config()->set('laravel-api.response.error_format', 'structured');

        $response = $this->renderer->render(new \RuntimeException('boom'), $this->jsonRequest());

        $this->assertNotNull($response);
        $payload = $response->getData(true);
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame('InternalServerError', $payload['error']['code']);
    }

    // -------------------------------------------------------------------------
    // register() wires into the exception handler
    // -------------------------------------------------------------------------

    public function test_register_wires_renderable_into_exception_handler(): void
    {
        // Should not throw; verifies the handler accepts our callback.
        $renderer = $this->app->make(ApiExceptionRenderer::class);
        $renderer->register();

        $this->assertTrue(true); // no exception = success
    }
}
