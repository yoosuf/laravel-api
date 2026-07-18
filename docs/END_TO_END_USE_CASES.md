# End-to-End Use Cases

Concrete, release-ready usage patterns for `yoosuf/laravel-api`.

---

## Use case 1: Full API setup with middleware, exception rendering, and response helpers

**Goal:** bootstrap a new Laravel API with consistent responses, automatic exception handling, and request tracing.

### 1 — Install and publish

```bash
composer require yoosuf/laravel-api:*
php artisan vendor:publish --tag=laravel-api-config
```

### 2 — Register middleware in `app/Http/Kernel.php`

```php
'api' => [
    \Yoosuf\LaravelApi\Http\Middleware\ForceJsonMiddleware::class,
    \Yoosuf\LaravelApi\Http\Middleware\RequestIdMiddleware::class,
    \Yoosuf\LaravelApi\Http\Middleware\SecurityHeadersMiddleware::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

### 3 — Enable exception rendering in `.env`

```
LARAVEL_API_EXCEPTIONS_AUTO_RENDER=true
```

This automatically converts `AuthenticationException`, `ValidationException`, `ModelNotFoundException`, and `HttpException` to consistent API responses.

### 4 — Use `HasApiResponses` in a controller

```php
use Yoosuf\LaravelApi\Concerns\HasApiResponses;

class OrderController extends Controller
{
    use HasApiResponses;

    public function index(): JsonResponse
    {
        return $this->fromPaginator(Order::paginate(20));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated());
        return $this->created($order, 'Order created', null, route('orders.show', $order));
    }

    public function show(Order $order): JsonResponse
    {
        $response = $this->success($order, 'Order fetched');
        return $this->checkEtag($request, $this->withEtag($response));
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();
        return $this->noContent();
    }
}
```

### Outcome

Every response has a predictable envelope. Every error — including framework exceptions — follows the same shape. Every response carries `X-Request-ID` for tracing.

---

## Use case 2: Paginated collection responses

**Goal:** return paginated lists with correct OData-lite body and pagination headers.

```php
// From a LengthAwarePaginator (standard)
public function index(): JsonResponse
{
    return $this->fromPaginator(Order::paginate(20));
}

// From a CursorPaginator
public function feed(): JsonResponse
{
    return $this->fromCursorPaginator(Post::cursorPaginate(10));
}

// Manual (custom items/total/links)
public function search(Request $request): JsonResponse
{
    $results = Search::run($request->query('q'));
    return $this->paginated(
        $results->items(),
        $results->total(),
        $results->nextPageUrl(),
        $results->previousPageUrl()
    );
}
```

**Response shape:**
```json
{
  "value": [ { "id": 1 }, { "id": 2 } ],
  "@count": 100,
  "@nextLink": "https://api.example.com/orders?page=2"
}
```

**Link header:**
```
Link: <https://api.example.com/orders?page=2>; rel="next"
```

---

## Use case 3: Rate-limiting with full headers

```php
public function store(Request $request): JsonResponse
{
    if ($this->isRateLimited($request)) {
        return $this->tooManyRequests(
            'Too many orders placed',
            null,
            null,
            60,    // Retry-After seconds
            1000,  // X-RateLimit-Limit
            0,     // X-RateLimit-Remaining
            time() + 60 // X-RateLimit-Reset
        );
    }
    // ...
}
```

---

## Use case 4: Structured error format for microservice / B2B APIs

**Goal:** emit RFC-style errors that downstream services can parse without guessing field names.

In `.env`:
```
LARAVEL_API_ERROR_FORMAT=structured
```

All errors now return:
```json
{
  "error": {
    "code": "UnprocessableEntity",
    "message": "One or more fields failed validation.",
    "details": [
      { "code": "ValidationError", "message": "The email field is required.", "target": "email" }
    ]
  }
}
```

Or use `structuredError()` explicitly for precise control:

```php
return $this->structuredError(
    'InsufficientFunds',
    'Account balance is below the required minimum.',
    422,
    'accountId',
    [['code' => 'BalanceCheck', 'message' => 'Balance: 0. Required: 100.', 'target' => 'balance']]
);
```

---

## Use case 5: API versioning

**Goal:** route clients to versioned surfaces and reject unknown versions.

In `config/laravel-api.php`:
```php
'versioning' => [
    'enabled'   => true,
    'supported' => ['1.0', '2.0'],
    'current'   => '2.0',
],
```

Register middleware on the API routes:
```php
Route::middleware(['api', 'laravel-api.versioning'])->prefix('api')->group(function () {
    // ...
});
```

Clients using `?api-version=9.9` receive:
```json
{
  "error": {
    "code": "UnsupportedApiVersion",
    "message": "The API version '9.9' is not supported. Supported versions: 1.0, 2.0.",
    "target": "api-version"
  }
}
```

---

## Use case 6: Deprecating a route version

**Goal:** signal to clients that an endpoint will be removed, without breaking them immediately.

```php
Route::middleware(['laravel-api.deprecation:2025-01-01,2026-01-01,https://api.example.com/v2/orders'])
    ->get('/api/v1/orders', [OrderV1Controller::class, 'index']);
```

Response headers:
```
Deprecation: Wed, 01 Jan 2025 00:00:00 GMT
Sunset:      Thu, 01 Jan 2026 00:00:00 GMT
Link:        <https://api.example.com/v2/orders>; rel="successor-version"
```

---

## Use case 7: OpenAPI docs with auto-inferred tags and security

**Goal:** generate a spec where operations are tagged by controller and auth-gated routes show the security requirement.

```bash
php artisan api:openapi --format=all --prefix=/api
```

The generated spec will contain:

- `tags: [orders]` on `OrderController` routes (auto-inferred)
- `security: [{ bearerAuth: [] }]` on routes with `auth:sanctum`
- `401`, `403` responses on auth-gated operations
- `422` responses on POST/PUT/PATCH
- `404` on routes with path parameters
- `429`, `500` on every operation

Enable the Swagger UI:
```php
'docs_ui' => ['enabled' => true, 'driver' => 'swagger', 'route' => 'api-docs'],
```

```bash
php artisan vendor:publish --tag=laravel-api-assets --force
php artisan serve
```

Open `http://localhost:8000/api-docs`.

---

## Use case 8: ETags for HTTP caching

**Goal:** enable conditional GET responses so clients skip re-downloading unchanged resources.

```php
public function show(Order $order): JsonResponse
{
    $response = $this->success($order);
    $response = $this->withEtag($response);          // Adds ETag header
    return $this->checkEtag($request, $response);    // Returns 304 if If-None-Match matches
}
```

Client request:
```
GET /api/orders/1
If-None-Match: W/"abc123"
```

Response when unchanged:
```
HTTP/1.1 304 Not Modified
```

---

## Use case 9: Testing with ApiResponseAssertions

**Goal:** write clean, readable API tests without raw array access.

```php
use Yoosuf\LaravelApi\Testing\ApiResponseAssertions;

class OrderTest extends TestCase
{
    use ApiResponseAssertions;

    public function test_list_is_paginated(): void
    {
        $this->assertApiPaginated($this->getJson('/api/orders'));
    }

    public function test_create_returns_201_with_location(): void
    {
        $response = $this->postJson('/api/orders', ['amount' => 100]);
        $this->assertApiCreated($response);
        $this->assertApiDataKey($response, 'id', 1);
        $response->assertHeader('Location');
    }

    public function test_validation_errors_are_structured(): void
    {
        $response = $this->postJson('/api/orders', []);
        $this->assertApiValidationError($response, 'amount');
    }

    public function test_request_id_is_present(): void
    {
        $this->assertApiHasRequestId($this->getJson('/api/orders'));
    }
}
```

---

## Use case 10: Health check for orchestration

**Goal:** expose a health endpoint for load balancers, Kubernetes liveness probes, and uptime monitors.

Enabled by default. Check at:
```
GET /_health
```

Response:
```json
{ "status": "ok", "timestamp": "2026-07-18T08:00:00Z" }
```

Customise route or disable:
```
LARAVEL_API_HEALTH_ENABLED=true
LARAVEL_API_HEALTH_ROUTE=health
```


## Use case 1: Ship machine-readable and human-readable API docs for an existing API

Goal: expose OpenAPI JSON, YAML, and a browser-based docs UI for an existing `/api/v1` surface.

### Steps

1. Install the package and publish config:

```bash
composer require yoosuf/laravel-api:*
php artisan vendor:publish --tag=laravel-api-config
```

2. Enable docs UI in `config/laravel-api.php`:

```php
'docs_route' => [
    'enabled' => true,
    'json' => 'openapi.json',
    'yaml' => 'openapi.yaml',
],
'docs_ui' => [
    'enabled' => true,
    'driver' => 'swagger',
    'route' => 'api-docs',
    'title' => 'Laradoc API Reference',
    'spec_url' => '/openapi.json',
    'middleware' => [],
],
```

3. Publish UI assets:

```bash
php artisan vendor:publish --tag=laravel-api-assets --force
```

4. Generate the files:

```bash
php artisan api:openapi --format=all --prefix=/api/v1
```

5. Start the app and verify the runtime endpoints:

```bash
php artisan serve --host=127.0.0.1 --port=8000
curl -i http://127.0.0.1:8000/openapi.json
curl -i http://127.0.0.1:8000/openapi.yaml
curl -i http://127.0.0.1:8000/api-docs
```

### Outcome

- API tooling can consume `/openapi.json` or `/openapi.yaml`.
- Humans can browse the API interactively at `/api-docs`.
- The docs UI stays tied to the live runtime schema through `spec_url`.

## Use case 2: Standardize controller responses across success, business failure, and server error cases

Goal: make response envelopes predictable across the API without repeating JSON formatting in every controller.

### Controller example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yoosuf\LaravelApi\Concerns\HasApiResponses;

class ApiPackageSampleController extends Controller
{
    use HasApiResponses;

    public function profile(): JsonResponse
    {
        return $this->responseSuccess([
            'id' => 101,
            'name' => 'Sample User',
            'role' => 'demo',
        ], 'Profile fetched successfully');
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'priority' => ['required', 'in:low,normal,high'],
            'items' => ['nullable', 'array'],
        ]);

        return $this->responseSuccess([
            'accepted' => true,
            'payload' => $validated,
        ], 'Payload accepted', 201);
    }

    public function fail(): JsonResponse
    {
        return $this->responseFailed(
            'Business rule conflict',
            409,
            ['rule' => ['This action cannot be performed in the current state.']]
        );
    }

    public function error(): JsonResponse
    {
        return $this->responseError(
            'Unexpected server error (demo)',
            500,
            ['trace' => 'simulated']
        );
    }
}
```

### Live responses from this workspace

`GET /api/v1/sample/profile` returns `200`:

```json
{"ok":true,"type":"success","message":"Profile fetched successfully","data":{"id":101,"name":"Sample User","role":"demo"},"meta":{},"errors":{}}
```

`POST /api/v1/sample/submit` returns `201`:

```json
{"ok":true,"type":"success","message":"Payload accepted","data":{"accepted":true,"payload":{"title":"My task","priority":"high","items":["one","two"]}},"meta":{},"errors":{}}
```

`GET /api/v1/sample/fail` returns `409`:

```json
{"ok":false,"type":"failed","message":"Business rule conflict","data":null,"meta":{},"errors":{"rule":["This action cannot be performed in the current state."]}}
```

`GET /api/v1/sample/error` returns `500`:

```json
{"ok":false,"type":"error","message":"Unexpected server error (demo)","data":null,"meta":{},"errors":{"trace":"simulated"}}
```

### Outcome

- Consumers can depend on one response envelope shape.
- Success, failure, and error responses differ by status code and `type`, not by random JSON structure.
- The same endpoints can then be documented consistently in OpenAPI.

## Use case 3: Generate docs from real application routes without manually writing a spec first

Goal: bootstrap documentation from the Laravel router and existing controller surface.

### Steps

1. Keep API routes under a stable prefix such as `/api/v1`.
2. Generate the spec from the application router:

```bash
php artisan api:openapi --format=all --prefix=/api/v1
```

3. Inspect the generated files:

```bash
cat docs/openapi.generated.json
cat docs/openapi.generated.yaml
```

4. Verify the expected endpoints appear in the JSON output.

In this workspace, the generated spec contains:

- `/sample/profile`
- `/sample/submit`
- `/sample/fail`
- `/sample/error`

### Outcome

- The package can bootstrap docs from the routes you already have.
- Teams can iterate on route annotations, overrides, and providers after getting a working baseline.

## Use case 4: Publish release-ready documentation for consumers of the package

Goal: make adoption straightforward for downstream Laravel teams.

### What to document for every release

1. Installation commands.
2. Published config defaults.
3. Runtime docs endpoints.
4. Docs UI endpoint.
5. Response helper API.
6. One working end-to-end example with actual payloads.

### Recommended release artifacts

- `README.md` for quick start
- `docs/CONFIG_REFERENCE.md` for config keys
- `docs/END_TO_END_USE_CASES.md` for real workflows
- `docs/RELEASE.md` for shipping and validation
- `CHANGELOG.md` for behavior changes
- `UPGRADE.md` when a release changes defaults or config semantics

### Outcome

- Consumers can install, verify, and adopt the package without guessing missing steps.
- Release validation stays tied to real use cases instead of abstract checklists.