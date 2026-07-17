# Configuration Reference

## Root key

All package settings are under laravel-api.openapi.

## Keys

### enabled

Type: bool

Enables or disables OpenAPI generation behavior.

### title, version, description, server_url

Types: string

Top-level OpenAPI info and server metadata.

### default_path_prefix

Type: string

Route prefix used for path inclusion when generating spec.

### output

Type: object

- json_path: target file path for generated JSON spec
- yaml_path: target file path for generated YAML spec

### docs_route

Type: object

- enabled: enable JSON/YAML runtime docs routes
- json: route path for JSON spec
- yaml: route path for YAML spec

### docs_ui

Type: object

- enabled: enable static docs page route
- driver: swagger or redoc
- route: UI route path, default `api-docs`
- title: UI page title
- spec_url: URL used by UI to fetch OpenAPI JSON, default `/openapi.json`
- middleware: list of middleware names applied to the UI route

### filters

Type: object

- include_routes: list of route names to include
- exclude_routes: list of route names to exclude
- middleware: list of middleware names that must exist on a route

### providers

Type: list of class strings

Provider classes implementing SchemaProvider and/or OperationOverrideProvider.

### action_map

Type: object

Controller action keyed fragments for operation merge.

Example key format:

App\\Http\\Controllers\\DocumentController@store

### overrides

Type: object

Manual operation overrides grouped by:

- routes: keyed by route name
- actions: keyed by controller action

### components.schemas

Type: object

Reusable schema definitions for components.schemas.

## Merge precedence

Generation follows this precedence from lower to higher:

1. auto-generated baseline operation
2. validation and resource inference
3. action_map
4. provider overrides
5. manual overrides from config

## Environment variable guidance

Use env vars for environment-specific values only:

- server_url
- docs routes/ui toggles
- output paths when required by deployment model

Keep schema and override definitions in config source control.
