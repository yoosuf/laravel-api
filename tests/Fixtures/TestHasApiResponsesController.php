<?php

namespace Yoosuf\LaravelApi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Yoosuf\LaravelApi\Concerns\HasApiResponses;

/**
 * A minimal controller that exercises every HasApiResponses shortcut so the
 * integration tests can verify the full middleware → controller → response chain.
 */
class TestHasApiResponsesController
{
    use HasApiResponses;

    public function ok(): JsonResponse
    {
        return $this->success(['id' => 1], 'OK');
    }

    public function created(): JsonResponse
    {
        // Call apiResponder() directly to avoid shadowing the trait's created().
        return $this->apiResponder()->created(['id' => 2], 'Created', null, '/api/users/2');
    }

    public function accepted(): JsonResponse
    {
        return $this->apiResponder()->accepted(null, 'Processing', null, '/api/jobs/99');
    }

    public function noContent(): JsonResponse
    {
        return $this->apiResponder()->noContent();
    }

    public function paginatedItems(): JsonResponse
    {
        return $this->paginated([['id' => 1], ['id' => 2]], 50, 'http://localhost/api/users?page=2', 'http://localhost/api/users?page=1');
    }

    public function notFoundError(): JsonResponse
    {
        return $this->notFound('User not found');
    }

    public function validationError(): JsonResponse
    {
        return $this->validation('Validation failed', ['email' => ['The email field is required.']]);
    }

    public function rateLimitError(): JsonResponse
    {
        return $this->tooManyRequests('Rate limit exceeded', null, null, 60, 100, 0, 1753000000);
    }

    public function etagResponse(): JsonResponse
    {
        $response = $this->success(['id' => 1]);

        return $this->withEtag($response);
    }
}
