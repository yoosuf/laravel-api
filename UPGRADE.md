# Upgrade Guide

## 1.0.0

Initial stable release.

### New capabilities

- Automatic OpenAPI generation from Laravel routes.
- Config-driven operation overrides and schema components.
- Validation and API resource inference helpers.
- Optional docs UI endpoint with Swagger UI or Redoc.

### Installation

If using local path repository in monorepo:

1. Ensure root composer.json includes the path repository for packages/yoosuf/laravel-api.
2. Require yoosuf/laravel-api.
3. Publish config with vendor:publish tag laravel-api-config.

### Runtime compatibility

- PHP: 8.1+
- Illuminate Support: 10.x, 11.x, 12.x

### No breaking changes from prior stable version

This is the first stable release.
