# Release and Publishing Guide

This document is the release gate for `yoosuf/laravel-api`. A release is not ready until installation, OpenAPI generation, runtime endpoints, and human-readable docs UI have all been exercised on a clean app.

## Release scope

Every release must prove these outcomes:

1. A Laravel app can install the package without manual source edits beyond documented setup.
2. `php artisan api:openapi` generates valid JSON and YAML files.
3. Runtime docs endpoints serve the latest schema.
4. The docs UI endpoint renders and loads the runtime JSON schema.
5. Response helpers continue to produce the documented envelope structure.

## Option A: Monorepo path package

Keep using local path repository in the root project:

- Keep root composer.json repository entry pointing to packages/yoosuf/laravel-api.
- Keep requirement as yoosuf/laravel-api:* (or pin exact versions if preferred).

## Option B: Publish to Packagist

1. Push package source to a dedicated GitHub repository, for example yoosuf/laravel-api.
2. Ensure composer.json metadata is correct:
   - name, description, license, homepage, support, require ranges.
3. Ensure the README and `docs/END_TO_END_USE_CASES.md` reflect the actual shipped endpoints and config defaults.
4. Tag a release:
   - git tag v1.0.0
   - git push origin v1.0.0
5. Submit repository URL in Packagist.
6. Enable auto-update webhook from Packagist to GitHub.

## Clean-install verification

Run this sequence from a Laravel application that requires the package:

```bash
composer require yoosuf/laravel-api:*
php artisan vendor:publish --tag=laravel-api-config
php artisan vendor:publish --tag=laravel-api-assets --force
php artisan api:openapi --format=all --prefix=/api/v1
php artisan route:list --path=openapi
php artisan route:list --path=api-docs
```

Expected results:

- `docs/openapi.generated.json` exists.
- `docs/openapi.generated.yaml` exists.
- Route list contains `openapi.json` and `openapi.yaml`.
- Route list contains the configured docs UI route such as `api-docs`.

Start the app and verify the runtime surface:

```bash
php artisan serve --host=127.0.0.1 --port=8000
curl -i http://127.0.0.1:8000/openapi.json
curl -i http://127.0.0.1:8000/openapi.yaml
curl -i http://127.0.0.1:8000/api-docs
```

Expected results:

- `/openapi.json` returns `200` with `Content-Type: application/json`.
- `/openapi.yaml` returns `200` with a YAML content type.
- `/api-docs` returns `200` with HTML containing the configured title and spec URL.

## Human-readable docs UI release check

The UI route is defined by config, not by code changes in the host app. Validate these settings before release:

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

Validation points:

1. `driver` is either `swagger` or `redoc`.
2. `route` is documented in release notes if changed.
3. `spec_url` resolves to the same machine-readable endpoint the UI should render.
4. UI assets were published when using the packaged stylesheet.
5. Any middleware requirement is intentional and documented.

## Pre-release checklist

1. Run quality checks:
   - composer release:check
2. Update CHANGELOG.md.
3. Update UPGRADE.md when release contains behavior changes.
4. Verify docs generation command output on a clean install.
5. Verify the docs UI route is reachable and renders successfully.
6. Confirm README runtime endpoints match current config defaults.
7. Confirm CI workflow passes on the release branch/tag.

## Release notes minimum content

Every tagged release should mention:

- supported Laravel and PHP versions
- default runtime docs endpoints
- whether docs UI is enabled by config or changed in behavior
- response helper additions or changes
- any config keys added, removed, or renamed

## Post-release smoke test

After tagging or publishing, re-install the released version in a separate Laravel app and repeat:

```bash
php artisan api:openapi --format=all --prefix=/api/v1
curl -i http://127.0.0.1:8000/openapi.json
curl -i http://127.0.0.1:8000/api-docs
```

Do not close the release until those checks pass on the released artifact, not only on the monorepo path package.
