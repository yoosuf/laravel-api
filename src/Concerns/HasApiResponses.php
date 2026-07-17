<?php

namespace Yoosuf\LaravelApi\Concerns;

use Illuminate\Http\JsonResponse;
use Yoosuf\LaravelApi\Http\ApiResponder;

trait HasApiResponses
{
    protected function apiResponder(): ApiResponder
    {
        /** @var ApiResponder $responder */
        $responder = app(ApiResponder::class);

        return $responder;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function responseSuccess(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        ?array $meta = null
    ): JsonResponse {
        return $this->apiResponder()->responseSuccess($data, $message, $status, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    protected function responseFailed(
        string $message = 'Request failed',
        int $status = 400,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->apiResponder()->responseFailed($message, $status, $errors, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    protected function responseError(
        string $message = 'Internal server error',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->apiResponder()->responseError($message, $status, $errors, $meta);
    }

    /**
     * Typo-compatible alias requested by the user.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    protected function responseErrror(
        string $message = 'Internal server error',
        int $status = 500,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        return $this->apiResponder()->responseErrror($message, $status, $errors, $meta);
    }
}
