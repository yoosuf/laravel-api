# yoosuf/laravel-api

High-performance Laravel API package for OpenAPI 3 generation, interactive docs, and predictable response envelopes.

Build Laravel APIs that ship machine-readable OpenAPI specs, a browser-friendly API reference, and consistent success or failure payloads from one package.

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

