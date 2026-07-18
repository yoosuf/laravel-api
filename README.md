# yoosuf/laravel-api

Production-ready Laravel API toolkit: consistent response envelopes, structured error formats, OpenAPI 3 generation, request correlation, API versioning, exception rendering, paginator adapters, ETag helpers, security headers, and a health check endpoint — all in one package.

## Features

### Response building
- Unified `ApiResponder` with every standard HTTP status shortcut (200–503)
- Two error formats: `envelope` (`ok / type / message / data / errors`) and `structured` (`error.code / message / target / details / innererror`)
- Paginated collection responses with OData-lite shape (`value / @count / @nextLink / @prevLink`) and RFC 5988 `Link` header
- `LengthAwarePaginator` and `CursorPaginator` adapters (`fromPaginator()` / `fromCursorPaginator()`)
- `Location` header on `created()` and `accepted()` (RFC 7231)
- `Retry-After` header on 429 / 503
- `X-RateLimit-Limit / Remaining / Reset` on 429
- ETag generation, conditional-request checking, and `304 Not Modified`
- Configurable envelope keys, default messages, and pagination keys

### Middleware
- `laravel-api.force-json` — forces `Accept: application/json` so exceptions render as JSON, not HTML
- `laravel-api.request-id` — attaches `X-Request-ID` and `X-Correlation-ID` to every request and response
- `laravel-api.security-headers` — `X-Content-Type-Options`, `X-Frame-Options`, `Cache-Control`, `Referrer-Policy`
- `laravel-api.versioning` — reads and validates `?api-version=` or `Api-Version:` header
- `laravel-api.deprecation` — sets `Deprecation:` and `Sunset:` headers on retiring routes

### Exception handling
- `ApiExceptionRenderer` converts `AuthenticationException`, `AuthorizationException`, `ValidationException`, `ModelNotFoundException`, `HttpException`, and generic `Throwable` into consistent API responses
- Opt-in auto-registration via `LARAVEL_API_EXCEPTIONS_AUTO_RENDER=true`

### OpenAPI generation
- Route-driven OpenAPI 3.0.3 spec from Laravel routes
- Tags auto-inferred from controller class name (`OrderController` → `orders`)
- Auth middleware (`auth:sanctum`, `auth:api`, etc.) → `security: [{ bearerAuth: [] }]`
- Standard reusable response definitions auto-added to `components.responses`
- Standard error schemas (`ErrorEnvelope`, `StructuredError`) in `components.schemas`
- `FormRequest` rules → `requestBody` schema inference
- `JsonResource` / `ResourceCollection` → response schema inference
- JSON and YAML export, runtime endpoints, optional Swagger UI / Redoc
- Spec caching with configurable store and TTL

### Health check
- `GET /_health` endpoint (configurable route, middleware, and on/off toggle)

### Testing utilities
- `ApiResponseAssertions` trait for fluent test assertions (`assertApiSuccess`, `assertApiPaginated`, `assertStructuredError`, etc.)

---

## Installation

### Path repository (monorepo / local)

Add the path repository in root `composer.json`:

```json
{
  "type": "path",
  "url": "packages/yoosuf/laravel-api",
  "options": { "symlink": true }
}
```

Require and publish:

```bash
composer require yoosuf/laravel-api:*
php artisan vendor:publish --tag=laravel-api-config
```

### Packagist (once published)

```bash
composer require yoosuf/laravel-api
php artisan vendor:publish --tag=laravel-api-config
```

---

## Quick start

### 1. Register recommended middleware

In `app/Http/Kernel.php`, add to the `api` middleware group:

```php
'api' => [
    \Yoosuf\LaravelApi\Http\Middleware\ForceJsonMiddleware::class,
    \Yoosuf\LaravelApi\Http\Middleware\RequestIdMiddleware::class,
    \Yoosuf\LaravelApi\Http\Middleware\SecurityHeadersMiddleware::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

Or use the named aliases registered by the package:

```php
'api' => [
    'laravel-api.force-json',
    'laravel-api.request-id',
    'laravel-api.security-headers',
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

### 2. Auto-register exception rendering (optional)

In `.env`:

```
LARAVEL_API_EXCEPTIONS_AUTO_RENDER=true
```

Or register manually in `AppServiceProvider::boot()`:

```php
app(\Yoosuf\LaravelApi\Exceptions\ApiExceptionRenderer::class)->register();
```

This converts `AuthenticationException`, `ValidationException`, `ModelNotFoundException`, and `HttpException` into consistent JSON API responses automatically.

### 3. Use `HasApiResponses` in controllers

```php
use Yoosuf\LaravelApi\Concerns\HasApiResponses;

class OrderController extends Controller
{
    use HasApiResponses;

    public function index(): JsonResponse
    {
        $orders = Order::paginate(20);
        return $this->fromPaginator($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated());
        return $this->created($order, 'Order created', null, route('orders.show', $order));
    }

    public function show(Order $order): JsonResponse
    {
        $response = $this->success($order);
        return $this->withEtag($response);
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();
        return $this->noContent();
    }
}
```

### 4. Or use the Facade / helpers directly

```php
use Yoosuf\LaravelApi\Facades\ApiResponse;

// Facade
return ApiResponse::success($data, 'Fetched');
return ApiResponse::fromPaginator($paginator);
return ApiResponse::validation('Invalid input', $errors);

// Global helpers
return response_success($data, 'Fetched');
return response_failed('Bad input', 422, $errors);
return api_paginated($items, $total, $nextUrl);
```

---

## Response formats

### Envelope format (default)

**Success:**
```json
{
  "ok": true,
  "type": "success",
  "message": "Fetched",
  "data": { "id": 1, "name": "Order #1" },
  "meta": {}
}
```

**Error:**
```json
{
  "ok": false,
  "type": "failed",
  "message": "Validation failed",
  "data": null,
  "meta": {},
  "errors": {
    "email": ["The email field is required."]
  }
}
```

### Structured format

Set `LARAVEL_API_ERROR_FORMAT=structured` to use the structured error format for all error responses:

```json
{
  "error": {
    "code": "UnprocessableEntity",
    "message": "Validation failed",
    "target": null,
    "details": [
      { "code": "ValidationError", "message": "The email field is required.", "target": "email" }
    ]
  }
}
```

Use `structuredError()` explicitly regardless of format setting:

```php
return $this->structuredError('ResourceNotFound', 'Order not found', 404, 'orderId');
```

### Paginated response

```json
{
  "value": [ { "id": 1 }, { "id": 2 } ],
  "@count": 100,
  "@nextLink": "https://api.example.com/orders?page=3",
  "@prevLink": "https://api.example.com/orders?page=1"
}
```

Response also includes `Link: <url>; rel="next", <url>; rel="prev"` header (RFC 5988).

---

## All response methods

### Success

| Method | Status | Notes |
|---|---|---|
| `success($data, $message, $status, $meta)` | 200 | Base success |
| `created($data, $message, $meta, $location)` | 201 | Sets `Location` header |
| `accepted($data, $message, $meta, $location)` | 202 | Sets `Location` header |
| `noContent()` | 204 | Empty body |
| `paginated($items, $total, $nextLink, $prevLink)` | 200 | OData-lite + `Link` header |
| `fromPaginator($paginator)` | 200 | From `LengthAwarePaginator` |
| `fromCursorPaginator($paginator)` | 200 | From `CursorPaginator` (no `@count`) |

### Client errors (4xx)

| Method | Status |
|---|---|
| `badRequest($message, $errors, $meta)` | 400 |
| `unauthorized($message, $errors, $meta)` | 401 |
| `forbidden($message, $errors, $meta)` | 403 |
| `notFound($message, $errors, $meta)` | 404 |
| `conflict($message, $errors, $meta)` | 409 |
| `gone($message, $errors, $meta)` | 410 |
| `validation($messageOrException, $errors, $meta)` | 422 |
| `unprocessable($message, $errors, $meta)` | 422 |
| `locked($message, $errors, $meta)` | 423 |
| `tooManyRequests($message, $errors, $meta, $retryAfter, $limit, $remaining, $reset)` | 429 |

### Server errors (5xx)

| Method | Status |
|---|---|
| `error($message, $status, $errors, $meta)` | 5xx |
| `notImplemented($message, $errors, $meta)` | 501 |
| `serviceUnavailable($message, $errors, $meta, $retryAfter)` | 503 |

### ETag / conditional requests

```php
$response = $this->success($data);
$response = $this->withEtag($response);          // adds ETag header
return $this->checkEtag($request, $response);    // returns 304 if If-None-Match matches
```

---

## Middleware reference

### ForceJsonMiddleware

Forces `Accept: application/json` so every request — including framework-level errors — returns JSON.

```php
// Kernel.php api group
'laravel-api.force-json',
```

### RequestIdMiddleware

Attaches `X-Request-ID` and `X-Correlation-ID` to every request and response.

```
LARAVEL_API_REQUEST_ID_ENABLED=true
LARAVEL_API_REQUEST_ID_HEADER=X-Request-ID
LARAVEL_API_CORRELATION_HEADER=X-Correlation-ID
```

### SecurityHeadersMiddleware

Adds `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Cache-Control: no-store`, `Referrer-Policy: strict-origin`.

### ApiVersionMiddleware

Reads `?api-version=` or `Api-Version:` header. Returns a structured 400 for unsupported versions.

```
LARAVEL_API_VERSIONING_ENABLED=true
LARAVEL_API_VERSION_CURRENT=1.0
```

Config: `laravel-api.versioning.supported = ['1.0', '2.0']`

### DeprecationMiddleware

Signals deprecation and removal date on a route group.

```php
Route::middleware(['laravel-api.deprecation:2025-01-01,2026-01-01'])->group(function () {
    // deprecated routes
});
```

Sets `Deprecation:` and `Sunset:` response headers automatically.

---

## Exception rendering

```php
// AppServiceProvider::boot()
app(\Yoosuf\LaravelApi\Exceptions\ApiExceptionRenderer::class)->register();
```

| Exception | Status |
|---|---|
| `AuthenticationException` | 401 |
| `AuthorizationException` | 403 |
| `ValidationException` | 422 with full error bag |
| `ModelNotFoundException` | 404 with model name |
| `HttpException` | matching status |
| `Throwable` | 500 (message hidden in production, shown with `APP_DEBUG=true`) |

Only renders for requests that send `Accept: application/json` or match `api/*`.

---

## Health check

Enabled by default at `GET /_health`:

```json
{ "status": "ok", "timestamp": "2026-07-18T08:00:00Z" }
```

```
LARAVEL_API_HEALTH_ENABLED=true
LARAVEL_API_HEALTH_ROUTE=_health
```

---

## OpenAPI generation

### Generate files

```bash
php artisan api:openapi
php artisan api:openapi --format=json
php artisan api:openapi --format=yaml --output=docs/openapi.v2.yaml
php artisan api:openapi --prefix=/api/v1
php artisan api:openapi --include-route=orders.index --middleware=api
```

### Auto-generated spec features

Every generated spec includes:

- **Tags** inferred from controller name (`OrderController` → `orders`)
- **`bearerAuth` security scheme** when any route uses auth middleware
- **`security: [{ bearerAuth: [] }]`** on auth-gated operations
- **Standard response refs**: `401`, `403`, `404` (path-param routes), `422` (POST/PUT/PATCH), `429`, `500` on every operation
- **`ErrorEnvelope`** and **`StructuredError`** schemas in `components.schemas`
- `requestBody` inferred from `FormRequest` rules
- Response schemas inferred from `JsonResource` / `ResourceCollection` return types

### Runtime endpoints

```
GET /openapi.json
GET /openapi.yaml
GET /api-docs       # optional Swagger UI or Redoc
GET /_health
```

### Schema providers

```php
// config/laravel-api.php
'providers' => [
    App\OpenApi\Providers\OrderSchemaProvider::class,
],
```

```php
class OrderSchemaProvider implements SchemaProvider
{
    public function schemas(): array
    {
        return [
            'Order' => [
                'type' => 'object',
                'properties' => [
                    'id'     => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'placed', 'fulfilled']],
                ],
            ],
        ];
    }
}
```

### Operation overrides

```php
'overrides' => [
    'routes' => [
        'orders.store' => ['post' => ['summary' => 'Place an order', 'tags' => ['orders']]],
    ],
    'actions' => [
        'App\Http\Controllers\OrderController@store' => [
            'post' => ['x-internal' => true],
        ],
    ],
],
```

---

## Testing with ApiResponseAssertions

Add the trait to your test case for fluent API response assertions:

```php
use Yoosuf\LaravelApi\Testing\ApiResponseAssertions;

class OrderTest extends TestCase
{
    use ApiResponseAssertions;

    public function test_index(): void
    {
        $response = $this->getJson('/api/orders');
        $this->assertApiPaginated($response);
    }

    public function test_store_validation(): void
    {
        $response = $this->postJson('/api/orders', []);
        $this->assertApiValidationError($response, 'amount');
    }

    public function test_store_success(): void
    {
        $response = $this->postJson('/api/orders', ['amount' => 100]);
        $this->assertApiCreated($response);
        $this->assertApiDataKey($response, 'id', 1);
    }

    public function test_rate_limiting(): void
    {
        $response = $this->getJson('/api/orders');
        $this->assertApiTooManyRequests($response, 60); // asserts Retry-After: 60
    }
}
```

Available assertions:

| Method | Description |
|---|---|
| `assertApiSuccess($response, $status)` | ok=true, envelope structure |
| `assertApiCreated($response)` | 201 + envelope |
| `assertApiAccepted($response)` | 202 + envelope |
| `assertApiNoContent($response)` | 204 |
| `assertApiPaginated($response)` | `value` + `@count` |
| `assertApiError($response, $status)` | ok=false |
| `assertApiValidationError($response, $field)` | 422 + optional field check |
| `assertApiUnauthorized($response)` | 401 |
| `assertApiForbidden($response)` | 403 |
| `assertApiNotFound($response)` | 404 |
| `assertApiTooManyRequests($response, $retryAfter)` | 429 + `Retry-After` |
| `assertStructuredError($response, $code, $status)` | structured format |
| `assertApiDataKey($response, $key, $value)` | `data.key` value |
| `assertApiMeta($response, $key, $value)` | `meta.key` value |
| `assertApiHasRequestId($response)` | `X-Request-ID` header |

---

## Configuration reference

Full config is in `config/laravel-api.php`. Key sections:

| Key | Default | Description |
|---|---|---|
| `openapi.enabled` | `true` | Enable OpenAPI generation |
| `openapi.title` | app name | Spec title |
| `openapi.version` | `1.0.0` | Spec version |
| `openapi.default_path_prefix` | `/api` | Route prefix filter |
| `openapi.cache.enabled` | `false` | Cache generated spec |
| `openapi.docs_route.enabled` | `true` | Serve runtime JSON/YAML |
| `openapi.docs_ui.enabled` | `false` | Enable Swagger/Redoc UI |
| `response.error_format` | `envelope` | `envelope` or `structured` |
| `response.envelope.*` | — | Configurable response keys |
| `response.pagination.*` | — | OData-lite key names |
| `request_id.enabled` | `true` | Attach correlation headers |
| `request_id.header` | `X-Request-ID` | Request ID header name |
| `versioning.enabled` | `false` | Validate `api-version` |
| `versioning.supported` | `[]` | Whitelist (empty = accept all) |
| `exceptions.auto_render` | `false` | Auto-register exception renderer |
| `health.enabled` | `true` | Enable health endpoint |
| `health.route` | `_health` | Health check URL path |

---

## Quality checks

Run from the package directory:

```bash
composer test              # all tests
composer test:unit
composer test:integration
composer analyse           # PHPStan level 5
composer lint              # Pint style check
composer release:check     # test + analyse + lint
```

---

## Changelog and upgrade

- Changelog: `CHANGELOG.md`
- Upgrade notes: `UPGRADE.md`
- Versioning policy: `docs/SEMVER_POLICY.md`

## Project documents

- `LICENSE` — MIT
- `CONTRIBUTING.md`
- `CODE_OF_CONDUCT.md`
- `SECURITY.md`
- `SUPPORT.md`


## Features

- Generate OpenAPI 3.0.3 specs from Laravel routes
- Export JSON and YAML spec files
- Serve live docs endpoints for JSON and YAML
- Configurable metadata, server URL, and API path prefix
- Action-based request and response mapping
- Route/action operation overrides (summary, tags, security, and more)
- Reusable `components.schemas` registry with provider support
- Auto-inference from `JsonResource`/`ResourceCollection` return types
- FormRequest validation rule inference for request schemas
- Route filtering by name and middleware from CLI options
- Optional static docs UI (Swagger UI or Redoc)
- Unified API response helpers (success, failed, error, validation, and aliases)

## Installation (local path repository)

1. Add path repository in root `composer.json`:

```json
{
  "type": "path",
  "url": "packages/yoosuf/laravel-api",
  "options": { "symlink": true }
}
```

1. Require package:

```bash
composer require yoosuf/laravel-api:*
```

1. Publish config:

```bash
php artisan vendor:publish --tag=laravel-api-config
```

1. (Optional) Publish docs UI assets:

```bash
php artisan vendor:publish --tag=laravel-api-assets
```

## Commands

Generate both outputs to configured paths:

```bash
php artisan api:openapi
```

Generate only JSON:

```bash
php artisan api:openapi --format=json
```

Generate YAML to custom file:

```bash
php artisan api:openapi --format=yaml --output=docs/openapi.v1.yaml
```

Limit generation to a prefix:

```bash
php artisan api:openapi --prefix=/api/v1
```

Generate with route filters:

```bash
php artisan api:openapi --include-route=documents.store --exclude-route=documents.destroy --middleware=api
```

## Runtime endpoints

- `GET /openapi.json`
- `GET /openapi.yaml`

Optional docs UI route:

- `GET /api-docs`

Routes are configurable in `config/laravel-api.php`.

## End-to-end quick start

1. Install the package and publish the config:

```bash
composer require yoosuf/laravel-api:*
php artisan vendor:publish --tag=laravel-api-config
```

1. Enable the human-readable docs UI in `config/laravel-api.php`:

```php
'docs_ui' => [
  'enabled' => true,
  'driver' => 'swagger',
  'route' => 'api-docs',
  'title' => 'Laradoc API Reference',
  'spec_url' => '/openapi.json',
  'middleware' => [],
],
```

1. Publish the UI assets:

```bash
php artisan vendor:publish --tag=laravel-api-assets --force
```

1. Generate the OpenAPI artifacts:

```bash
php artisan api:openapi --format=all --prefix=/api/v1
```

1. Open the machine-readable and human-readable endpoints:

- `http://127.0.0.1:8000/openapi.json`
- `http://127.0.0.1:8000/openapi.yaml`
- `http://127.0.0.1:8000/api-docs`

The integration guide in `docs/PROJECT_INTEGRATION_GUIDE.md` demonstrates that full flow with real endpoints and payloads.

## API response helpers

Use the responder to avoid repeating JSON response structures in controllers.

Options:

- Facade: `ApiResponse::responseSuccess(...)`
- Helper: `response_success(...)`
- Service: `app('laravel-api.response')->success(...)`
- Trait: `Yoosuf\\LaravelApi\\Concerns\\HasApiResponses`

Examples:

```php
return response_success(['user' => $user], 'Fetched');
return response_failed('Validation failed', 422, ['email' => ['required']]);
return response_error('Unexpected failure', 500);
```

Alias support (including user-requested typo compatibility):

- `responseSuccess`
- `responseFailed`
- `responseError`
- `responseErrror`

## Quality and CI

Run package quality checks from the package directory:

```bash
composer test
composer test:unit
composer test:integration
composer analyse
composer lint
```

GitHub Actions workflow is provided at `.github/workflows/laravel-api-quality.yml`.

## Versioning policy

This package follows Semantic Versioning.

- MAJOR: backward-incompatible API changes.
- MINOR: backward-compatible feature additions.
- PATCH: backward-compatible bug fixes.

Public API surface is defined in `docs/SEMVER_POLICY.md`.

## Upgrade and changelog

- Changelog: `CHANGELOG.md`
- Upgrade notes: `UPGRADE.md`

## Open source project documents

- License: `LICENSE`
- Contributing: `CONTRIBUTING.md`
- Code of Conduct: `CODE_OF_CONDUCT.md`
- Security Policy: `SECURITY.md`
- Support Guide: `SUPPORT.md`
- Detailed contributor workflow: `docs/CONTRIBUTING_GUIDELINES.md`

## Documentation map

- Architecture: `docs/ARCHITECTURE.md`
- Configuration reference: `docs/CONFIG_REFERENCE.md`
- Operations runbook: `docs/OPERATIONS_RUNBOOK.md`
- End-to-end use cases: `docs/END_TO_END_USE_CASES.md`
- SemVer and public API policy: `docs/SEMVER_POLICY.md`
- Release process: `docs/RELEASE.md`
- Future engineering pipeline: `docs/FUTURE_PIPELINE.md`
- Contribution process: `docs/CONTRIBUTING_GUIDELINES.md`
- Project integration guide: `docs/PROJECT_INTEGRATION_GUIDE.md`

## Release and publishing

Before tagging a release, run:

```bash
composer release:check
```

For monorepo-only usage, keep requiring via path repository.
For distribution, publish to Packagist using the process in `docs/RELEASE.md`.

## Phase 2 configuration hooks

- `openapi.providers`: class list for custom providers implementing:
  - `Yoosuf\\LaravelApi\\OpenApi\\Contracts\\SchemaProvider`
  - `Yoosuf\\LaravelApi\\OpenApi\\Contracts\\OperationOverrideProvider`
- `openapi.action_map`: map by `Controller@method` to merge request/response schema fragments.
- `openapi.overrides`: operation overrides grouped by `routes` and `actions`.
- `openapi.components.schemas`: reusable component schemas.

