# Operations Runbook

## Local developer workflow

1. Install dependencies in package directory.
2. Run quality checks.
3. Generate OpenAPI outputs.
4. Verify docs routes or docs UI.

Suggested command sequence:

- composer release:check
- php artisan api:openapi --format=all

## Production performance profile

Recommended settings for high-throughput APIs:

- Enable OpenAPI cache in config (`openapi.cache.enabled = true`).
- Use an application cache store for docs generation (`redis` or `memcached`).
- Set a practical `ttl_seconds` for docs freshness/performance balance.
- Bump `openapi.cache.bust_token` on route/config changes requiring instant refresh.

Response helper performance:

- Response envelope/default keys are loaded once into the singleton responder.
- Use helpers/facade/trait to avoid repeated payload assembly boilerplate.

## Troubleshooting

### OpenAPI file not generated

Checks:

- Confirm laravel-api.openapi.enabled is true.
- Confirm output paths are writable.
- Confirm filters are not excluding all routes.

### No paths in generated spec

Checks:

- Validate default_path_prefix and command --prefix.
- Verify include_routes and middleware filters.
- Verify application routes are loaded in test/runtime context.
- If cache is enabled, bump `openapi.cache.bust_token` and regenerate.

### Missing requestBody inference

Checks:

- Confirm action uses FormRequest parameter.
- Confirm rules() returns array-compatible rules.
- Add explicit action_map override for complex scenarios.

### Missing response schema inference

Checks:

- Confirm return type hints use JsonResource or ResourceCollection.
- For advanced serialization, add explicit components and overrides.

### Docs UI not loading

Checks:

- Confirm docs_ui.enabled is true.
- Confirm docs_ui.spec_url points to reachable JSON endpoint.
- Confirm published assets exist in public/vendor/laravel-api.

## CI/CD usage

Run package checks in CI:

- unit tests
- integration tests
- phpstan analysis
- pint style checks

Use composer release:check as single pre-release gate.

## Release checklist summary

1. Update changelog and upgrade guide.
2. Run composer release:check.
3. Generate sample spec and verify output.
4. Tag release.
5. Publish or update Packagist.
