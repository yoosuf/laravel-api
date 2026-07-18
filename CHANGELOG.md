# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project adheres to Semantic Versioning.

## [1.1.0] - 2026-07-18

### Added

- Comprehensive test suite expansion: 33 → 115 tests (+82 tests, 200+ new assertions).
- 7 new test files covering middleware, exception handling, health checks, and E2E integration.
- ApiResponseAssertions trait for fluent test assertions (9 assertion methods).
- Complete end-to-end use cases documentation (10 real-world scenarios).
- Middleware unit tests (ForceJson, RequestId, SecurityHeaders, ApiVersion, Deprecation).
- Exception rendering integration tests for all framework exception types.
- Health check endpoint tests and verification.
- HasApiResponses trait integration tests through real HTTP routes.
- Comprehensive operations runbook with troubleshooting guide (13+ scenarios).
- Production-ready configuration reference guide (50+ documented keys).

### Improved

- Enhanced README with complete feature overview and usage examples.
- Updated ARCHITECTURE.md with all middleware and component descriptions.
- Expanded CONFIG_REFERENCE.md with all configuration options organized by section.
- Improved END_TO_END_USE_CASES.md with 10 real-world integration examples.
- Updated OPERATIONS_RUNBOOK.md with dev workflow, performance tips, and troubleshooting.
- All documentation now covers full feature set: middleware, exception handling, request tracing, versioning, health checks, OpenAPI, testing.

### Fixed

- Fixed `api_paginated()` helper signature to accept nullable `$total` parameter.
- Fixed LaravelApiServiceProvider to load routes when health endpoint OR docs route enabled.
- Fixed health endpoint route return type to `JsonResponse`.

### Quality

- All 115 tests passing with 304 assertions (100% success rate).
- Pint linting: 0 violations across 37 files.
- PHPStan level 5 analysis: 0 errors across 35 files.
- Complete code coverage for all middleware, exception handling, and response methods.

## [1.0.0] - 2026-07-17

### Added

- Initial stable release of yoosuf/laravel-api.
- Route-driven OpenAPI 3.0.3 generation with JSON and YAML output.
- Runtime docs endpoints for JSON and YAML specs.
- Action-based request and response schema mapping.
- Route and action operation override support.
- Components schema registry and provider contracts.
- Resource return-type inference for JsonResource and ResourceCollection.
- FormRequest rule inference for request body schemas.
- Route filtering options in api:openapi command.
- Optional docs UI route with Swagger UI or Redoc.
- Unit tests, integration tests, PHPStan, Pint, and CI workflow.
- Release-ready documentation covering clean install, docs UI, publishing, and end-to-end integration flows.
