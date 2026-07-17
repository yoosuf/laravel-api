# Project Integration Guide

This guide shows a full application-level integration of `yoosuf/laravel-api` with real routes, real responses, generated OpenAPI files, and a browser-based API docs UI.

## Outcome

At the end of this flow you will have:

- API endpoints returning a consistent response envelope
- generated `docs/openapi.generated.json`
- generated `docs/openapi.generated.yaml`
- runtime schema endpoints at `/openapi.json` and `/openapi.yaml`
- a human-readable docs UI at `/api-docs`

## 1. Add the package to a Laravel app

If you are developing locally in a monorepo, register the path repository:

```json
{
  "type": "path",
  "url": "packages/yoosuf/laravel-api",
  "options": { "symlink": true }
}
```

Then require the package:

```bash
composer require yoosuf/laravel-api:*
php artisan vendor:publish --tag=laravel-api-config
php artisan vendor:publish --tag=laravel-api-assets --force
```

## 2. Configure the docs endpoints

In `config/laravel-api.php`:

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

## 3. Add API routes using the response helpers

Example routes:

```php
Route::prefix('v1')->group(function (): void {
    Route::get('/sample/profile', [ApiPackageSampleController::class, 'profile'])->name('sample.profile');
    Route::post('/sample/submit', [ApiPackageSampleController::class, 'submit'])->name('sample.submit');
    Route::get('/sample/fail', [ApiPackageSampleController::class, 'fail'])->name('sample.fail');
    Route::get('/sample/error', [ApiPackageSampleController::class, 'error'])->name('sample.error');
});
```

Example controller:

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

## 4. Generate the OpenAPI files

```bash
php artisan api:openapi --format=all --prefix=/api/v1
```

Generated artifacts:

- `docs/openapi.generated.json`
- `docs/openapi.generated.yaml`

## 5. Verify the live endpoints

```bash
php artisan serve --host=127.0.0.1 --port=8000
curl -i -s http://127.0.0.1:8000/api/v1/sample/profile
curl -i -s -X POST http://127.0.0.1:8000/api/v1/sample/submit -H 'Content-Type: application/json' -d '{"title":"My task","priority":"high","items":["one","two"]}'
curl -i -s http://127.0.0.1:8000/api/v1/sample/fail
curl -i -s http://127.0.0.1:8000/api/v1/sample/error
curl -i -s http://127.0.0.1:8000/openapi.json
curl -i -s http://127.0.0.1:8000/api-docs
```

## 6. Live response examples

`GET /api/v1/sample/profile` returns:

```json
{"ok":true,"type":"success","message":"Profile fetched successfully","data":{"id":101,"name":"Sample User","role":"demo"},"meta":{},"errors":{}}
```

`POST /api/v1/sample/submit` returns:

```json
{"ok":true,"type":"success","message":"Payload accepted","data":{"accepted":true,"payload":{"title":"My task","priority":"high","items":["one","two"]}},"meta":{},"errors":{}}
```

`GET /api/v1/sample/fail` returns:

```json
{"ok":false,"type":"failed","message":"Business rule conflict","data":null,"meta":{},"errors":{"rule":["This action cannot be performed in the current state."]}}
```

`GET /api/v1/sample/error` returns:

```json
{"ok":false,"type":"error","message":"Unexpected server error (demo)","data":null,"meta":{},"errors":{"trace":"simulated"}}
```

## 7. What consumers get

- one package for response formatting and API docs
- live OpenAPI endpoints for tooling
- a browser-based docs UI for humans
- a documented path from install to release