# Semantic Versioning and Public API Policy

## Semantic Versioning

This package follows Semantic Versioning:

- MAJOR version increments for backward-incompatible changes.
- MINOR version increments for backward-compatible new features.
- PATCH version increments for backward-compatible bug fixes.

## Public API surface

The following are considered public API and are covered by SemVer guarantees:

- Service provider: Yoosuf\\LaravelApi\\LaravelApiServiceProvider
- Console command signature: api:openapi and supported flags
- Config structure rooted at laravel-api.openapi
- Contracts:
  - Yoosuf\\LaravelApi\\OpenApi\\Contracts\\SchemaProvider
  - Yoosuf\\LaravelApi\\OpenApi\\Contracts\\OperationOverrideProvider
- Runtime docs routes controlled by config values

## Non-public internals

The following may change in minor releases if not documented as public:

- Internal mapper implementation details
- Internal helper classes and private/protected methods
- Test fixtures and CI internals

## Deprecation policy

- Deprecations are introduced in MINOR releases and documented in CHANGELOG.md.
- Deprecated behavior remains available until the next MAJOR release where practical.
