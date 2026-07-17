<?php

namespace Yoosuf\LaravelApi\Http;

use Illuminate\Http\JsonResponse;

class ApiResponder
{
    /**
     * @var array<string, string>
     */
    private array $envelope;

    /**
     * @var array<string, string>
     */
    private array $defaults;

    public function __construct()
    {
        $this->envelope = [
            'ok_key' => (string) config('laravel-api.response.envelope.ok_key', 'ok'),
            'type_key' => (string) config('laravel-api.response.envelope.type_key', 'type'),
            'message_key' => (string) config('laravel-api.response.envelope.message_key', 'message'),
            'data_key' => (string) config('laravel-api.response.envelope.data_key', 'data'),
            'meta_key' => (string) config('laravel-api.response.envelope.meta_key', 'meta'),
            'errors_key' => (string) config('laravel-api.response.envelope.errors_key', 'errors'),
        ];

        $this->defaults = [
            'success_message' => (string) config('laravel-api.response.defaults.success_message', 'Success'),
            'failed_message' => (string) config('laravel-api.response.defaults.failed_message', 'Request failed'),
            'error_message' => (string) config('laravel-api.response.defaults.error_message', 'Internal server error'),
        ];
    }

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
     * @param  array<string, mixed>|null  $meta
     */
    public function created(
        mixed $data = null,
        string $message = 'Created',
        ?array $meta = null
    ): JsonResponse {
        return $this->success($data, $message, 201, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function accepted(
        mixed $data = null,
        string $message = 'Accepted',
        ?array $meta = null
    ): JsonResponse {
        return $this->success($data, $message, 202, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function noContent(string $message = 'No Content', ?array $meta = null): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function failed(
        string $message = '',
        int $status = 400,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        $payload = $this->basePayload(
            false,
            'failed',
            $message !== '' ? $message : $this->defaultMessage('failed_message', 'Request failed'),
            null,
            $meta,
            $errors
        );

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function error(
        string $message = '',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        $payload = $this->basePayload(
            false,
            'error',
            $message !== '' ? $message : $this->defaultMessage('error_message', 'Internal server error'),
            null,
            $meta,
            $errors
        );

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function validation(
        string $message = 'Validation error',
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, 422, $errors, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function notFound(
        string $message = 'Resource not found',
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, 404, $errors, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function unauthorized(
        string $message = 'Unauthorized',
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, 401, $errors, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function forbidden(
        string $message = 'Forbidden',
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, 403, $errors, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function conflict(
        string $message = 'Conflict',
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, 409, $errors, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function tooManyRequests(
        string $message = 'Too many requests',
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, 429, $errors, $meta);
    }

    /**
     * Backward-compatible alias requested by user wording.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function responseSuccess(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        ?array $meta = null
    ): JsonResponse {
        return $this->success($data, $message, $status, $meta);
    }

    /**
     * Backward-compatible alias requested by user wording.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function responseFailed(
        string $message = '',
        int $status = 400,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->failed($message, $status, $errors, $meta);
    }

    /**
     * Backward-compatible alias requested by user wording.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function responseErrror(
        string $message = '',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->error($message, $status, $errors, $meta);
    }

    /**
     * Canonical alias with correct spelling.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function responseError(
        string $message = '',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->error($message, $status, $errors, $meta);
    }

    public function badRequest(string $message = 'Bad request', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 400, $errors, $meta);
    }

    public function unprocessable(string $message = 'Unprocessable entity', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->failed($message, 422, $errors, $meta);
    }

    public function unavailable(string $message = 'Service unavailable', ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return $this->error($message, 503, $errors, $meta);
    }

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
        return $this->defaults[$configKey] ?? $fallback;
    }

    protected function envelopeKey(string $configKey, string $fallback): string
    {
        return $this->envelope[$configKey] ?? $fallback;
    }
}
