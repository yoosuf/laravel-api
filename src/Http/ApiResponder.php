<?php

namespace Yoosuf\LaravelApi\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Central response builder for the laravel-api package.
 *
 * Supports two error formats controlled by config('laravel-api.response.error_format'):
 *   - 'envelope'   (default) — { ok, type, message, data, meta, errors }
 *   - 'structured' — { error: { code, message, target, details, innererror } }
 *
 * Config is read lazily — changing config at runtime (e.g., in tests) is always
 * reflected without needing to re-instantiate the class.
 *
 * Request correlation headers (X-Request-ID, X-Correlation-ID) are handled by
 * RequestIdMiddleware, which should be registered on the api middleware stack.
 */
class ApiResponder
{
    // No eager config reads — all values resolved lazily via envelopeKey() / defaultMessage().

    // -------------------------------------------------------------------------
    // Success responses
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function success(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        ?array $meta = null
    ): JsonResponse {
        $payload = $this->basePayload(
            true,
            'success',
            $message !== '' ? $message : $this->defaultMessage('success_message', 'Success'),
            $data,
            $meta,
            null
        );

        return response()->json($payload, $status);
    }

    /**
     * HTTP 201 Created. Optionally sets a Location header pointing to the new resource
     * per RFC 7231 §7.1.2.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function created(
        mixed $data = null,
        string $message = 'Created',
        ?array $meta = null,
        ?string $location = null
    ): JsonResponse {
        $response = $this->success($data, $message, 201, $meta);

        if ($location !== null) {
            $response->headers->set('Location', $location);
        }

        return $response;
    }

    /**
     * HTTP 202 Accepted. Optionally sets a Location header for polling the async result.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function accepted(
        mixed $data = null,
        string $message = 'Accepted',
        ?array $meta = null,
        ?string $location = null
    ): JsonResponse {
        $response = $this->success($data, $message, 202, $meta);

        if ($location !== null) {
            $response->headers->set('Location', $location);
        }

        return $response;
    }

    /**
     * HTTP 204 No Content. Body is intentionally empty.
     */
    public function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return a paginated collection response (OData-lite conventions).
     *
     * Shape (keys configurable via laravel-api.response.pagination):
     * {
     *   "value":     [ ... ],
     *   "@count":    100,          // omitted when $total is null (cursor pagination)
     *   "@nextLink": "https://…",  // omitted when null
     *   "@prevLink": "https://…",  // omitted when null
     * }
     *
     * A Link header (RFC 5988) is also set when links are present and
     * laravel-api.response.pagination.link_header is true (default).
     *
     * @param  array<int|string, mixed>  $items
     */
    public function paginated(
        array $items,
        ?int $total = null,
        ?string $nextLink = null,
        ?string $prevLink = null,
        int $status = 200
    ): JsonResponse {
        $valueKey = (string) config('laravel-api.response.pagination.value_key', 'value');
        $countKey = (string) config('laravel-api.response.pagination.count_key', '@count');
        $nextKey = (string) config('laravel-api.response.pagination.next_link_key', '@nextLink');
        $prevKey = (string) config('laravel-api.response.pagination.prev_link_key', '@prevLink');

        $payload = [$valueKey => $items];

        if ($total !== null) {
            $payload[$countKey] = $total;
        }

        if ($nextLink !== null) {
            $payload[$nextKey] = $nextLink;
        }

        if ($prevLink !== null) {
            $payload[$prevKey] = $prevLink;
        }

        $response = response()->json($payload, $status);

        // RFC 5988 Link header for clients that prefer headers over body
        if ((bool) config('laravel-api.response.pagination.link_header', true)) {
            $links = [];
            if ($nextLink !== null) {
                $links[] = "<{$nextLink}>; rel=\"next\"";
            }
            if ($prevLink !== null) {
                $links[] = "<{$prevLink}>; rel=\"prev\"";
            }
            if ($links !== []) {
                $response->headers->set('Link', implode(', ', $links));
            }
        }

        return $response;
    }

    /**
     * Build a paginated response directly from a Laravel LengthAwarePaginator.
     *
     * Automatically extracts items, total, next/prev page URLs so callers do
     * not need to call ->items(), ->total(), etc. manually.
     */
    public function fromPaginator(LengthAwarePaginator $paginator, int $status = 200): JsonResponse
    {
        return $this->paginated(
            array_values($paginator->items()),
            $paginator->total(),
            $paginator->hasMorePages() ? (string) $paginator->nextPageUrl() : null,
            $paginator->currentPage() > 1 ? (string) $paginator->previousPageUrl() : null,
            $status
        );
    }

    /**
     * Build a paginated response from a Laravel CursorPaginator.
     *
     * Cursor paginators do not expose a total count, so @count is omitted
     * from the response body.
     */
    public function fromCursorPaginator(CursorPaginator $paginator, int $status = 200): JsonResponse
    {
        return $this->paginated(
            array_values($paginator->items()),
            null, // total unknown for cursor pagination
            $paginator->hasMorePages() ? (string) $paginator->nextPageUrl() : null,
            $paginator->previousPageUrl() !== null ? (string) $paginator->previousPageUrl() : null,
            $status
        );
    }

    // -------------------------------------------------------------------------
    // Error responses  (4xx / 5xx)
    // -------------------------------------------------------------------------

    /**
     * Generic client-error response (4xx).
     *
     * When config('laravel-api.response.error_format') is 'structured' the
     * response body follows the structured error format; otherwise the
     * configurable envelope is used.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta  ignored in structured format
     */
    public function failed(
        string $message = '',
        int $status = 400,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        $message = $message !== '' ? $message : $this->defaultMessage('failed_message', 'Request failed');

        if ($this->isStructuredErrorFormat()) {
            return response()->json(
                $this->buildStructuredErrorPayload($this->statusToErrorCode($status), $message, null, $errors),
                $status
            );
        }

        $payload = $this->basePayload(false, 'failed', $message, null, $meta, $errors
        );

        return response()->json($payload, $status);
    }

    /**
     * Generic server-error response (5xx).
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta  ignored in structured format
     */
    public function error(
        string $message = '',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        $message = $message !== '' ? $message : $this->defaultMessage('error_message', 'Internal server error');

        if ($this->isStructuredErrorFormat()) {
            return response()->json(
                $this->buildStructuredErrorPayload($this->statusToErrorCode($status), $message, null, $errors),
                $status
            );
        }

        $payload = $this->basePayload(false, 'error', $message, null, $meta, $errors);

        return response()->json($payload, $status);
    }

    /**
     * Emit a fully explicit structured-format error response regardless of the
     * configured error_format setting. Use this when you need precise control
     * over code, target, and structured details.
     *
     * @param  array<int, array<string, mixed>>  $details
     * @param  array<string, mixed>|null  $innererror
     */
    public function structuredError(
        string $code,
        string $message,
        int $status = 400,
        ?string $target = null,
        array $details = [],
        ?array $innererror = null
    ): JsonResponse {
        return response()->json(
            $this->buildStructuredErrorPayload($code, $message, $target, null, $details, $innererror),
            $status
        );
    }

    // -------------------------------------------------------------------------
    // Named HTTP-status shortcuts
    // -------------------------------------------------------------------------

    public function badRequest(string $message = 'Bad request', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 400, $errors, $meta);
    }

    public function unauthorized(string $message = 'Unauthorized', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 401, $errors, $meta);
    }

    public function forbidden(string $message = 'Forbidden', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 403, $errors, $meta);
    }

    public function notFound(string $message = 'Resource not found', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 404, $errors, $meta);
    }

    public function conflict(string $message = 'Conflict', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 409, $errors, $meta);
    }

    /** HTTP 410 — resource permanently removed. */
    public function gone(string $message = 'Resource no longer available', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 410, $errors, $meta);
    }

    /**
     * HTTP 422 Validation error.
     *
     * Accepts either a plain message string or a ValidationException directly,
     * in which case the error bag is extracted automatically.
     *
     * @param  array<string, mixed>|null  $errors
     */
    public function validation(string|ValidationException $message = 'Validation failed', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        if ($message instanceof ValidationException) {
            return $this->failed($message->getMessage(), 422, $message->errors(), $meta);
        }

        return $this->failed($message, 422, $errors, $meta);
    }

    /**
     * HTTP 422 — generic unprocessable entity (non-validation use cases).
     *
     * Use validation() when you have field-level errors from a FormRequest or
     * Validator. Use unprocessable() for domain-level 422 errors (e.g. business
     * rule violations) where no field map is available.
     *
     * @param  array<string, mixed>|null  $errors
     */
    public function unprocessable(string $message = 'Unprocessable entity', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 422, $errors, $meta);
    }

    /** HTTP 423 — resource is locked (e.g. concurrent edit conflict). */
    public function locked(string $message = 'Resource is locked', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 423, $errors, $meta);
    }

    /**
     * HTTP 429 — Too Many Requests.
     *
     * Sets Retry-After (RFC 6585) and the standard X-RateLimit-* headers when
     * provided, giving clients the information they need to back off correctly.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function tooManyRequests(
        string $message = 'Too many requests',
        ?array $errors = null,
        ?array $meta = null,
        ?int $retryAfter = null,
        ?int $limit = null,
        ?int $remaining = null,
        ?int $reset = null
    ): JsonResponse {
        $response = $this->failed($message, 429, $errors, $meta);

        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }
        if ($limit !== null) {
            $response->headers->set('X-RateLimit-Limit', (string) $limit);
        }
        if ($remaining !== null) {
            $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        }
        if ($reset !== null) {
            $response->headers->set('X-RateLimit-Reset', (string) $reset);
        }

        return $response;
    }

    /** HTTP 501 — functionality not yet implemented. */
    public function notImplemented(string $message = 'Not implemented', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->error($message, 501, $errors, $meta);
    }

    /**
     * HTTP 503 — Service Unavailable.
     *
     * Optionally sets a `Retry-After` header (seconds) per RFC 7231 and
     * REST API guidelines §7.12.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function serviceUnavailable(
        string $message = 'Service unavailable',
        ?array $errors = null,
        ?array $meta = null,
        ?int $retryAfter = null
    ): JsonResponse {
        $response = $this->error($message, 503, $errors, $meta);

        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    /** @deprecated Use serviceUnavailable() instead. */
    public function unavailable(string $message = 'Service unavailable', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->serviceUnavailable($message, $errors, $meta);
    }

    // -------------------------------------------------------------------------
    // ETag / conditional-request helpers
    // -------------------------------------------------------------------------

    /**
     * Attach an ETag header to an existing response.
     *
     * The tag is an MD5 of the serialised response body, prefixed with W/ for
     * weak ETags. Pair with checkEtag() to short-circuit to 304 when the client
     * already holds the current representation.
     */
    public function withEtag(JsonResponse $response, bool $weak = true): JsonResponse
    {
        $prefix = $weak ? 'W/' : '';
        $tag = $prefix . '"' . md5((string) $response->getContent()) . '"';
        $response->headers->set('ETag', $tag);

        return $response;
    }

    /**
     * Return 304 Not Modified if the client's If-None-Match matches the ETag
     * on the response; otherwise return the original response unchanged.
     *
     * Typical usage:
     *
     *   $response = $this->success($data);
     *   $response = $this->apiResponder()->withEtag($response);
     *   return $this->apiResponder()->checkEtag($request, $response);
     */
    public function checkEtag(\Illuminate\Http\Request $request, JsonResponse $response): JsonResponse
    {
        $etag = $response->headers->get('ETag', '');

        if ($etag !== '' && $request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        return $response;
    }

    /**
     * HTTP 304 Not Modified. Body is intentionally empty.
     */
    public function notModified(): JsonResponse
    {
        return response()->json(null, 304);
    }

    // -------------------------------------------------------------------------
    // Backward-compatible aliases
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>|null  $meta */
    public function responseSuccess(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        ?array $meta = null
    ): JsonResponse {
        return $this->success($data, $message, $status, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    public function responseFailed(
        string $message = '',
        int $status = 400,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, $status, $errors, $meta);
    }

    /** @param  array<string, mixed>|null  $errors */
    public function responseError(
        string $message = '',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->error($message, $status, $errors, $meta);
    }

    /**
     * @deprecated Typo alias kept for backward compatibility. Use responseError().
     *
     * @param  array<string, mixed>|null  $errors
     */
    public function responseErrror(
        string $message = '',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->error($message, $status, $errors, $meta);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>|null  $errors
     * @return array<string, mixed>
     */
    protected function basePayload(
        bool $ok,
        string $type,
        string $message,
        mixed $data,
        ?array $meta,
        ?array $errors
    ): array {
        return [
            $this->envelopeKey('ok_key', 'ok') => $ok,
            $this->envelopeKey('type_key', 'type') => $type,
            $this->envelopeKey('message_key', 'message') => $message,
            $this->envelopeKey('data_key', 'data') => $data,
            $this->envelopeKey('meta_key', 'meta') => $meta ?? (object) [],
            $this->envelopeKey('errors_key', 'errors') => $errors ?? (object) [],
        ];
    }

    protected function defaultMessage(string $configKey, string $fallback): string
    {
        return (string) config("laravel-api.response.defaults.{$configKey}", $fallback);
    }

    protected function envelopeKey(string $configKey, string $fallback): string
    {
        return (string) config("laravel-api.response.envelope.{$configKey}", $fallback);
    }

    /**
     * Returns true when the structured error format is active.
     * Exposed publicly so ApiExceptionRenderer can check the mode.
     */
    public function isStructuredMode(): bool
    {
        return $this->isStructuredErrorFormat();
    }

    private function isStructuredErrorFormat(): bool
    {
        return (string) config('laravel-api.response.error_format', 'envelope') === 'structured';
    }

    /**
     * Maps an HTTP status code to a PascalCase REST API error code string.
     */
    private function statusToErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'BadRequest',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'NotFound',
            405 => 'MethodNotAllowed',
            408 => 'RequestTimeout',
            409 => 'Conflict',
            410 => 'Gone',
            422 => 'UnprocessableEntity',
            423 => 'Locked',
            429 => 'TooManyRequests',
            500 => 'InternalServerError',
            501 => 'NotImplemented',
            503 => 'ServiceUnavailable',
            default => 'Error',
        };
    }

    /**
     * Build a REST API guidelines §7 error payload.
     *
     * When $rawErrors is provided and $details is empty, the errors array is
     * automatically normalised into the `details` array so callers do not need
     * to restructure existing error bags.
     *
     * @param  array<string, mixed>|null  $rawErrors  existing errors bag (optional)
     * @param  array<int, array<string, mixed>>  $details  pre-built details entries
     * @param  array<string, mixed>|null  $innererror  diagnostic context (server-side only)
     * @return array<string, mixed>
     */
    private function buildStructuredErrorPayload(
        string $code,
        string $message,
        ?string $target,
        ?array $rawErrors,
        array $details = [],
        ?array $innererror = null
    ): array {
        if ($details === [] && $rawErrors !== null && $rawErrors !== []) {
            $details = $this->normaliseErrorsToDetails($rawErrors);
        }

        $error = ['code' => $code, 'message' => $message];

        if ($target !== null) {
            $error['target'] = $target;
        }

        $error['details'] = $details;

        if ($innererror !== null) {
            $error['innererror'] = $innererror;
        }

        return ['error' => $error];
    }

    /**
     * Convert a Laravel-style validation errors bag into the structured details
     * array format: [{ "code", "message", "target" }, ...].
     *
     * @param  array<string, mixed>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function normaliseErrorsToDetails(array $errors): array
    {
        $details = [];

        foreach ($errors as $field => $messages) {
            $messages = is_array($messages) ? $messages : [$messages];

            foreach ($messages as $msg) {
                $detail = [
                    'code' => 'ValidationError',
                    'message' => is_string($msg) ? $msg : (string) json_encode($msg),
                ];

                if (is_string($field) && $field !== '') {
                    $detail['target'] = $field;
                }

                $details[] = $detail;
            }
        }

        return $details;
    }
}
