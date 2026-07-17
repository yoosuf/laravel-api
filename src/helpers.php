<?php

use Illuminate\Http\JsonResponse;

if (! function_exists('api_response')) {
    function api_response(): \Yoosuf\LaravelApi\Http\ApiResponder
    {
        /** @var \Yoosuf\LaravelApi\Http\ApiResponder $responder */
        $responder = app('laravel-api.response');

        return $responder;
    }
}

if (! function_exists('response_success')) {
    /**
     * @param  array<string, mixed>|null  $meta
     */
    function response_success(mixed $data = null, string $message = 'Success', int $status = 200, ?array $meta = null): JsonResponse
    {
        return api_response()->responseSuccess($data, $message, $status, $meta);
    }
}

if (! function_exists('response_failed')) {
    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    function response_failed(string $message = 'Request failed', int $status = 400, ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return api_response()->responseFailed($message, $status, $errors, $meta);
    }
}

if (! function_exists('response_error')) {
    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    function response_error(string $message = 'Internal server error', int $status = 500, ?array $errors = null, ?array $meta = null): JsonResponse
    {
        return api_response()->responseErrror($message, $status, $errors, $meta);
    }
}
