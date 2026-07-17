# Development Plan: yoosuf/laravel-api

## Goal

Build a reusable Laravel package that standardizes API tooling and supports OpenAPI 3+ generation and delivery.

## Phase 1: Foundation (current scaffold)

- [x] Package skeleton with Composer autoload and Laravel service provider
	- Implemented in `packages/yoosuf/laravel-api/composer.json` and `src/LaravelApiServiceProvider.php`.
- [x] Config file for OpenAPI metadata, output paths, and docs routes
	- Implemented in `config/laravel-api.php`.
- [x] OpenAPI generator that inspects Laravel routes
	- Implemented in `src/OpenApi/OpenApiGenerator.php`.
- [x] Artisan command (`api:openapi`) for JSON and YAML export
	- Implemented in `src/Console/Commands/GenerateOpenApiCommand.php`.
- [x] Runtime routes to serve generated docs
	- Implemented in `routes/laravel-api.php` with JSON and YAML endpoints.

Phase 1 status: Complete.

## Phase 2: Schema and Validation Support

- [x] Add contract/interfaces for schema providers
	- Implemented in `src/OpenApi/Contracts/SchemaProvider.php` for provider-based schema registration.
	- Implemented in `src/OpenApi/Contracts/OperationOverrideProvider.php` for per-action operation metadata overrides.
	- Registered via config key `laravel-api.openapi.providers`.
- [x] Add request/response schema mapping by controller action
	- Implemented action map in `config/laravel-api.php` as `laravel-api.openapi.action_map` keyed by `Controller@method`.
	- Supports `requestBody`, responses, and media-type definitions per action.
	- Merged into operations in `src/OpenApi/OpenApiGenerator.php`.
- [x] Allow manual operation metadata overrides (summary, tags, security)
	- Implemented config key `laravel-api.openapi.overrides` for route and action-based overrides.
	- Supports overriding `summary`, `description`, `tags`, `security`, `deprecated`, and `operationId`.
	- Applied after route inspection so explicit config overrides generated values.
- [x] Add reusable components section generation (`components.schemas`)
	- Implemented collector service in `src/OpenApi/Support/ComponentsRegistry.php`.
	- Merges schemas from config (`laravel-api.openapi.components.schemas`) and registered providers.
	- Ensures `$ref` values targeting `#/components/schemas/*` are backed by schema entries.

Phase 2 status: Complete.

## Phase 3: DX Improvements

- [x] Add API resources to OpenAPI schema auto-mapping
	- Implemented resource analyzer in `src/OpenApi/Support/ResourceSchemaMapper.php`.
	- Detects `JsonResource` and `ResourceCollection` return types from controller action signatures.
	- Generates component schemas and attaches `$ref` response schemas.
- [x] Add support for request validation rules to infer schema constraints
	- Implemented validation mapper in `src/OpenApi/Support/ValidationRuleMapper.php`.
	- Resolves rules from `FormRequest` action parameters.
	- Maps common rules (`required`, `string`, `numeric`, `array`, `email`, `min`, `max`, `in`) to OpenAPI constraints.
- [x] Add command options for include/exclude route names and middleware filters
	- Extended `api:openapi` with `--include-route`, `--exclude-route`, and `--middleware`.
	- Added generator-level filtering support before operation generation.
	- Supports repeatable and comma-separated values.
- [x] Add optional static docs page with Swagger UI or Redoc
	- Added view and static assets in `resources/views` and `resources/assets`.
	- Added `laravel-api.openapi.docs_ui` config section.
	- Added docs UI route in `routes/laravel-api.php` that points to the configured JSON spec URL.

Phase 3 status: Complete.

## Phase 4: Quality and CI

- [x] Add unit tests for generator behavior and YAML/JSON outputs
	- Added unit suite in `tests/Unit/OpenApiGeneratorTest.php`.
	- Covers route discovery, JSON/YAML output rendering, schema inference, and route filtering behavior.
- [x] Add integration tests against test routes
	- Added integration suite in `tests/Integration/OpenApiCommandTest.php`.
	- Verifies `api:openapi` command output generation against fixture routes.
- [x] Add static analysis (PHPStan) and coding style checks (Pint)
	- Added `phpstan.neon` and `pint.json` in package root.
	- Added composer scripts: `analyse` and `lint`.
- [x] Add GitHub Actions for test and package validation
	- Added workflow at `.github/workflows/laravel-api-quality.yml`.
	- Runs unit tests, integration tests, PHPStan, and Pint on package changes.

Phase 4 status: Complete.

## Phase 5: Versioning and Release

- [x] Stabilize public API and semantic versioning policy
	- Added SemVer and API-surface policy in `docs/SEMVER_POLICY.md`.
	- Added release quality gate script `composer release:check`.
	- Established first stable release target as tag `v1.0.0`.
- [x] Add changelog and upgrade guide
	- Added package changelog at `CHANGELOG.md`.
	- Added upgrade guide at `UPGRADE.md`.
- [x] Publish package to Packagist (or keep as path repository for monorepo use)
	- Added publishing/runbook documentation in `docs/RELEASE.md`.
	- Added Packagist-ready metadata in `composer.json` (`homepage`, `support`).
	- Added forward roadmap and delivery matrix in `docs/FUTURE_PIPELINE.md`.

Phase 5 status: Complete.

## Milestones

1. M1: Scaffold complete and command outputs valid OpenAPI 3 files
2. M2: Schema inference and operation overrides available
3. M3: Test suite and CI pipeline green
4. M4: First stable release (`1.0.0`)
