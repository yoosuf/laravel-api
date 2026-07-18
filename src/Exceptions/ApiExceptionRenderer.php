<?php

namespace Yoosuf\LaravelApi\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Yoosuf\LaravelApi\Http\ApiResponder;

/**
 * Converts framework and application exceptions into consistent API JSON responses.
 *
 * Register it in AppServiceProvider::boot() (Laravel 10+):
 *
 *   app(ApiExceptionRenderer::class)->register();
 *
 * Or call it from your existing Handler:
 *
 *   $this->renderable(fn (Throwable $e, Request $req) => app(ApiExceptionRenderer::class)->render($e, $req));
 *
 * The renderer only takes over requests that send or accept JSON (API routes).
 * Non-JSON requests fall through to the default Laravel handler.
 */
class ApiExceptionRenderer
{
    public function __construct(
        private readonly ApiResponder $responder
    ) {}

    /**
     * Register this renderer into Laravel's exception handler.
     *
     * Calling this once (e.g., in AppServiceProvider::boot) is enough.
     * The renderer is opt-in so teams that have custom exception handling
     * are not forced to use it.
     */
    public function register(): void
    {
        app(ExceptionHandler::class)->renderable(
            fn (Throwable $e, Request $request): ?JsonResponse => $this->render($e, $request)
        );
    }

    /**
     * Render an exception as a JSON API response.
     *
     * Returns null for non-JSON requests so the default HTML handler is used.
     */
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $this->shouldRenderAsJson($request)) {
            return null;
        }

        return match (true) {
            $e instanceof AuthenticationException => $this->handleAuthentication($e),
            $e instanceof AuthorizationException => $this->handleAuthorization($e),
            $e instanceof ValidationException => $this->handleValidation($e),
            $e instanceof ModelNotFoundException => $this->handleModelNotFound($e),
            $e instanceof HttpExceptionInterface => $this->handleHttpException($e),
            default => $this->handleGeneric($e),
        };
    }

    private function shouldRenderAsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*')
            || $request->is('*/api/*');
    }

    private function handleAuthentication(AuthenticationException $e): JsonResponse
    {
        return $this->responder->unauthorized($e->getMessage() ?: 'Unauthenticated.');
    }

    private function handleAuthorization(AuthorizationException $e): JsonResponse
    {
        return $this->responder->forbidden($e->getMessage() ?: 'This action is unauthorized.');
    }

    private function handleValidation(ValidationException $e): JsonResponse
    {
        return $this->responder->validation($e->getMessage(), $e->errors());
    }

    private function handleModelNotFound(ModelNotFoundException $e): JsonResponse
    {
        $message = 'No ' . class_basename($e->getModel()) . ' was found with the given identifier.';

        return $this->responder->notFound($message);
    }

    private function handleHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        return match ($status) {
            401 => $this->responder->unauthorized($e->getMessage() ?: 'Unauthorized.'),
            403 => $this->responder->forbidden($e->getMessage() ?: 'Forbidden.'),
            404 => $this->responder->notFound($e->getMessage() ?: 'Resource not found.'),
            405 => $this->responder->failed($e->getMessage() ?: 'Method not allowed.', 405),
            429 => $this->responder->tooManyRequests(
                $e->getMessage() ?: 'Too many requests.',
                null,
                null,
                $this->retryAfterFromException($e)
            ),
            default => $status >= 500
                ? $this->responder->error($e->getMessage() ?: 'Server error.', $status)
                : $this->responder->failed($e->getMessage() ?: 'Request failed.', $status),
        };
    }

    private function handleGeneric(Throwable $e): JsonResponse
    {
        $debug = (bool) config('app.debug', false);

        $innererror = $debug ? [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ] : null;

        if ($this->responder->isStructuredMode()) {
            return $this->responder->structuredError(
                'InternalServerError',
                $debug ? $e->getMessage() : 'An unexpected error occurred.',
                500,
                null,
                [],
                $innererror
            );
        }

        return $this->responder->error(
            $debug ? $e->getMessage() : 'An unexpected error occurred.',
            500
        );
    }

    private function retryAfterFromException(HttpExceptionInterface $e): ?int
    {
        $headers = $e->getHeaders();

        if (isset($headers['Retry-After'])) {
            return (int) $headers['Retry-After'];
        }

        return null;
    }
}
