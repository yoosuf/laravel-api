# End-to-End Use Cases

This document shows concrete, release-ready ways to use `yoosuf/laravel-api` in a Laravel application. These are full flows, not placeholders.

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