<?php

namespace Yoosuf\LaravelApi\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Yoosuf\LaravelApi\Http\ApiResponder;

/**
 * Provides all ApiResponder shortcuts as protected controller methods.
 *
 * Register RequestIdMiddleware on the api middleware stack to ensure every
 * response carries X-Request-ID / X-Correlation-ID headers automatically.
 */
trait HasApiResponses
{
    protected function apiResponder(): ApiResponder
    {
        /** @var ApiResponder $responder */
        $responder = app(ApiResponder::class);

        return $responder;
    }

    // -------------------------------------------------------------------------
    // Success
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>|null  $meta */
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->success($data, $message, $status, $meta);
    }

    /** @param  array<string, mixed>|null  $meta */
    protected function created(mixed $data = null, string $message = 'Created', ?array $meta = null, ?string $location = null): JsonResponse
    {
        return $this->apiResponder()->created($data, $message, $meta, $location);
    }

    /** @param  array<string, mixed>|null  $meta */
    protected function accepted(mixed $data = null, string $message = 'Accepted', ?array $meta = null, ?string $location = null): JsonResponse
    {
        return $this->apiResponder()->accepted($data, $message, $meta, $location);
    }

    protected function noContent(): JsonResponse
    {
        return $this->apiResponder()->noContent();
    }

    /**
     * Paginated collection.
     *
     * @param  array<int|string, mixed>  $items
     */
    protected function paginated(
        array $items,
        ?int $total = null,
        ?string $nextLink = null,
        ?string $prevLink = null,
        int $status = 200
    ): JsonResponse {
        return $this->apiResponder()->paginated($items, $total, $nextLink, $prevLink, $status);
    }

    /** Paginated response from a LengthAwarePaginator. */
    protected function fromPaginator(LengthAwarePaginator $paginator, int $status = 200): JsonResponse
    {
        return $this->apiResponder()->fromPaginator($paginator, $status);
    }

    /** Paginated response from a CursorPaginator. */
    protected function fromCursorPaginator(CursorPaginator $paginator, int $status = 200): JsonResponse
    {
        return $this->apiResponder()->fromCursorPaginator($paginator, $status);
    }

    // -------------------------------------------------------------------------
    // Client errors (4xx)
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>|null  $errors */
    protected function badRequest(string $message = 'Bad request', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->badRequest($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function unauthorized(string $message = 'Unauthorized', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->unauthorized($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function forbidden(string $message = 'Forbidden', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->forbidden($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function notFound(string $message = 'Resource not found', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->notFound($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function conflict(string $message = 'Conflict', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->conflict($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function gone(string $message = 'Resource no longer available', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->gone($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function validation(string|ValidationException $message = 'Validation failed', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->validation($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function locked(string $message = 'Resource is locked', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->locked($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function tooManyRequests(string $message = 'Too many requests', ?array $errors = null, ?array $meta = null, ?int $retryAfter = null, ?int $limit = null, ?int $remaining = null, ?int $reset = null): JsonResponse
    {
        return $this->apiResponder()->tooManyRequests($message, $errors, $meta, $retryAfter, $limit, $remaining, $reset);
    }

    // -------------------------------------------------------------------------
    // Server errors (5xx)
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>|null  $errors */
    protected function serverError(string $message = 'Internal server error', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->error($message, 500, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function notImplemented(string $message = 'Not implemented', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->notImplemented($message, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function serviceUnavailable(string $message = 'Service unavailable', ?array $errors = null, ?array $meta = null, ?int $retryAfter = null): JsonResponse
    {
        return $this->apiResponder()->serviceUnavailable($message, $errors, $meta, $retryAfter);
    }

    // -------------------------------------------------------------------------
    // ETag / conditional-request helpers
    // -------------------------------------------------------------------------

    protected function withEtag(JsonResponse $response, bool $weak = true): JsonResponse
    {
        return $this->apiResponder()->withEtag($response, $weak);
    }

    protected function checkEtag(Request $request, JsonResponse $response): JsonResponse
    {
        return $this->apiResponder()->checkEtag($request, $response);
    }

    protected function notModified(): JsonResponse
    {
        return $this->apiResponder()->notModified();
    }

    /**
     * Emit an explicit structured-format error response.
     *
     * @param  array<int, array<string, mixed>>  $details
     * @param  array<string, mixed>|null  $innererror
     */
    protected function structuredError(
        string $code,
        string $message,
        int $status = 400,
        ?string $target = null,
        array $details = [],
        ?array $innererror = null
    ): JsonResponse {
        return $this->apiResponder()->structuredError($code, $message, $status, $target, $details, $innererror);
    }

    // -------------------------------------------------------------------------
    // Backward-compatible aliases
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>|null  $meta */
    protected function responseSuccess(mixed $data = null, string $message = 'Success', int $status = 200, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->responseSuccess($data, $message, $status, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function responseFailed(string $message = 'Request failed', int $status = 400, ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->responseFailed($message, $status, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    protected function responseError(string $message = 'Internal server error', int $status = 500, ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->responseError($message, $status, $errors, $meta);
    }

    /**
     * @deprecated Typo alias kept for backward compatibility. Use responseError().
     *
     * @param  array<string, mixed>|null  $errors
     */
    protected function responseErrror(string $message = 'Internal server error', int $status = 500, ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->apiResponder()->responseErrror($message, $status, $errors, $meta);
    }
}
