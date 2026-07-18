# Operations Runbook

## Local developer workflow

```bash
# From packages/yoosuf/laravel-api
composer install
composer test              # 115 tests — unit + integration
composer analyse           # PHPStan level 5
composer lint              # Pint style check
composer release:check     # all three gates in sequence
```

Generate docs from the host app:

```bash
php artisan api:openapi --format=all
php artisan serve
curl -s http://127.0.0.1:8000/_health | jq .
```

---

## Production performance profile

### Response building

- `ApiResponder` reads config lazily on each call — no pre-load cost in the constructor.
- The singleton is registered with `app->singleton()`, so it is instantiated once per request cycle.
- Use `HasApiResponses` trait in controllers to eliminate repeated `app(ApiResponder::class)->...` calls.

### OpenAPI spec generation

- Cache the generated spec in production: `openapi.cache.enabled = true`.
- Use a persistent cache store (Redis, Memcached): `openapi.cache.store = redis`.
- Set a practical `ttl_seconds` (e.g. `3600` for hourly refresh).
- Bump `openapi.cache.bust_token` when routes or config change between deploys.

### Health check

- The `/_health` endpoint has no middleware and performs no I/O by default.
- It is suitable as a Kubernetes liveness probe with zero overhead.
- To check downstream dependencies, extend the health route handler in the host app.

---

## Troubleshooting

### Health endpoint returns 404

- Verify `LARAVEL_API_HEALTH_ENABLED=true` in `.env`.
- The health route is loaded when `health.enabled` OR `openapi.docs_route.enabled` is true. If both are false, the routes file is not loaded.

### Exceptions still rendering as HTML

- Register `ForceJsonMiddleware` on the `api` middleware group.
- Enable `LARAVEL_API_EXCEPTIONS_AUTO_RENDER=true` or call `ApiExceptionRenderer::register()` manually in `AppServiceProvider::boot()`.
- Ensure the request sends `Accept: application/json` or targets an `api/*` path.

### Request ID not appearing on responses

- Confirm `laravel-api.request-id.enabled = true` (default is true).
- Confirm `RequestIdMiddleware` is registered in the `api` middleware group or on the route.

### Unsupported API version not being rejected

- Set `laravel-api.versioning.enabled = true`.
- Populate `laravel-api.versioning.supported` with allowed versions.
- Register `ApiVersionMiddleware` (`laravel-api.versioning`) on the route group.

### OpenAPI file not generated

- Confirm `laravel-api.openapi.enabled = true`.
- Confirm output paths are writable.
- Confirm filters are not excluding all routes.

### No paths in generated spec

- Validate `default_path_prefix` and `--prefix` argument match your route prefix.
- Check `include_routes` and `middleware` filters.
- If cache is enabled, bump `openapi.cache.bust_token` and regenerate.

### Tags missing from generated spec

- Tags are inferred from the controller class name suffix (`OrderController` → `orders`). Closure-based routes get no tags.
- Add explicit tags via `action_map` or `overrides`.

### No `security` on auth-gated operations

- The generator detects `auth`, `auth:api`, `auth:sanctum`, and `auth:passport` middleware on routes.
- Ensure the route's middleware list includes one of these strings.

### Missing `requestBody` inference

- Confirm the action method type-hints a `FormRequest` parameter.
- Confirm `rules()` returns an array-compatible value.
- Add an explicit `action_map` entry for complex scenarios.

### Missing response schema inference

- Confirm the action return type hint uses `JsonResource` or `ResourceCollection`.
- For advanced cases, add explicit `components.schemas` entries.

### ETag not appearing

- Call `withEtag($response)` on the response before returning it.
- `checkEtag($request, $response)` returns `304` only when `ETag` is set AND `If-None-Match` matches.

### Deprecation headers not appearing

- Ensure `laravel-api.deprecation` middleware is registered and applied to the route group.
- Pass at least one non-empty argument (e.g. `'true'` for an undated deprecation notice).

---

## CI / CD

```yaml
# Example GitHub Actions step
- name: Quality checks
  run: |
    cd packages/yoosuf/laravel-api
    composer install --no-interaction
    composer release:check
```

`composer release:check` runs: tests → PHPStan → Pint (in that order).

---

## Release checklist

1. Update `CHANGELOG.md` and `UPGRADE.md`.
2. Run `composer release:check` — all three gates must be green.
3. Generate a sample spec and verify output.
4. Bump version in `composer.json`.
5. Tag the release.
6. Publish / push to Packagist.


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
