# Configuration Reference

All settings live under the `laravel-api` root key in `config/laravel-api.php`.

---

## `openapi` — OpenAPI generation

### Core metadata

| Key | Type | Default | Description |
|---|---|---|---|
| `enabled` | bool | `true` | Enable or disable spec generation |
| `title` | string | app name | Spec `info.title` |
| `version` | string | `1.0.0` | Spec `info.version` |
| `description` | string | `""` | Spec `info.description` |
| `server_url` | string | `{APP_URL}/api` | Spec `servers[0].url` |
| `default_path_prefix` | string | `/api` | Route prefix filter for path inclusion |

### `output` — file paths

| Key | Default |
|---|---|
| `json_path` | `docs/openapi.generated.json` |
| `yaml_path` | `docs/openapi.generated.yaml` |

### `cache` — spec caching

| Key | Default | Description |
|---|---|---|
| `enabled` | `false` | Cache generated spec |
| `store` | `null` | Laravel cache store (`redis`, `array`, etc.) |
| `ttl_seconds` | `300` | Cache TTL |
| `key_prefix` | `laravel_api_openapi` | Cache key prefix |
| `bust_token` | `v1` | Change this to invalidate cache without a deploy |

### `docs_route` — runtime endpoints

| Key | Default |
|---|---|
| `enabled` | `true` |
| `json` | `openapi.json` |
| `yaml` | `openapi.yaml` |

### `docs_ui` — browser UI

| Key | Default | Options |
|---|---|---|
| `enabled` | `false` | |
| `driver` | `swagger` | `swagger`, `redoc` |
| `route` | `api-docs` | |
| `title` | `API Documentation` | |
| `spec_url` | `/openapi.json` | |
| `middleware` | `[]` | Middleware applied to the UI route |

### `filters` — route filtering

| Key | Type | Description |
|---|---|---|
| `include_routes` | `string[]` | Route names to include (empty = all) |
| `exclude_routes` | `string[]` | Route names to exclude |
| `middleware` | `string[]` | Only include routes with these middleware |

### `providers` — schema / override providers

```php
'providers' => [
    App\OpenApi\Providers\OrderSchemaProvider::class,
],
```

### `action_map` — per-action fragment injection

```php
'action_map' => [
    'App\Http\Controllers\OrderController@store' => [
        'post' => [
            'summary' => 'Place an order',
            'tags' => ['orders'],
        ],
    ],
],
```

### `overrides` — route and action overrides

```php
'overrides' => [
    'routes' => [
        'orders.index' => ['get' => ['summary' => 'List orders']],
    ],
    'actions' => [
        'App\Http\Controllers\OrderController@destroy' => [
            'delete' => ['x-internal' => true],
        ],
    ],
],
```

### `components.schemas` — reusable schemas

```php
'components' => [
    'schemas' => [
        'Money' => [
            'type' => 'object',
            'properties' => [
                'amount'   => ['type' => 'integer'],
                'currency' => ['type' => 'string'],
            ],
        ],
    ],
],
```

### Merge precedence (lowest → highest)

1. Auto-generated baseline
2. Validation / resource inference
3. `action_map`
4. Provider overrides
5. Manual `overrides` from config

---

## `response` — response shape

| Key | Default | Description |
|---|---|---|
| `error_format` | `envelope` | `envelope` or `structured` |
| `defaults.success_message` | `Success` | Default success message |
| `defaults.failed_message` | `Request failed` | Default failed message |
| `defaults.error_message` | `Internal server error` | Default error message |

### `envelope` — configurable response keys

```php
'envelope' => [
    'ok_key'      => 'ok',
    'type_key'    => 'type',
    'message_key' => 'message',
    'data_key'    => 'data',
    'meta_key'    => 'meta',
    'errors_key'  => 'errors',
],
```

### `pagination` — OData-lite collection keys

| Key | Default | Env |
|---|---|---|
| `value_key` | `value` | `LARAVEL_API_PAGINATION_VALUE_KEY` |
| `next_link_key` | `@nextLink` | `LARAVEL_API_PAGINATION_NEXT_LINK_KEY` |
| `prev_link_key` | `@prevLink` | `LARAVEL_API_PAGINATION_PREV_LINK_KEY` |
| `count_key` | `@count` | `LARAVEL_API_PAGINATION_COUNT_KEY` |
| `link_header` | `true` | `LARAVEL_API_PAGINATION_LINK_HEADER` |

---

## `request_id` — correlation headers

| Key | Default | Env |
|---|---|---|
| `enabled` | `true` | `LARAVEL_API_REQUEST_ID_ENABLED` |
| `header` | `X-Request-ID` | `LARAVEL_API_REQUEST_ID_HEADER` |
| `correlation_header` | `X-Correlation-ID` | `LARAVEL_API_CORRELATION_HEADER` |

---

## `versioning` — API versioning

| Key | Default | Env |
|---|---|---|
| `enabled` | `false` | `LARAVEL_API_VERSIONING_ENABLED` |
| `query_param` | `api-version` | `LARAVEL_API_VERSION_QUERY_PARAM` |
| `header` | `Api-Version` | `LARAVEL_API_VERSION_HEADER` |
| `current` | `1.0` | `LARAVEL_API_VERSION_CURRENT` |
| `supported` | `[]` | Whitelist — empty accepts any value |

---

## `exceptions` — exception rendering

| Key | Default | Env |
|---|---|---|
| `auto_render` | `false` | `LARAVEL_API_EXCEPTIONS_AUTO_RENDER` |

---

## `health` — health check endpoint

| Key | Default | Env |
|---|---|---|
| `enabled` | `true` | `LARAVEL_API_HEALTH_ENABLED` |
| `route` | `_health` | `LARAVEL_API_HEALTH_ROUTE` |
| `middleware` | `[]` | Middleware applied to the health route |


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
