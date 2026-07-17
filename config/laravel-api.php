<?php

return [
    'openapi' => [
        'enabled' => env('LARAVEL_API_OPENAPI_ENABLED', true),
        'title' => env('LARAVEL_API_OPENAPI_TITLE', config('app.name', 'Laravel API')),
        'version' => env('LARAVEL_API_OPENAPI_VERSION', '1.0.0'),
        'description' => env('LARAVEL_API_OPENAPI_DESCRIPTION', 'API documentation generated from Laravel routes.'),
        'server_url' => env('LARAVEL_API_OPENAPI_SERVER_URL', rtrim(config('app.url', 'http://localhost'), '/') . '/api'),
        'default_path_prefix' => env('LARAVEL_API_OPENAPI_PATH_PREFIX', '/api'),
        'output' => [
            'json_path' => env('LARAVEL_API_OPENAPI_JSON_PATH', 'docs/openapi.generated.json'),
            'yaml_path' => env('LARAVEL_API_OPENAPI_YAML_PATH', 'docs/openapi.generated.yaml'),
        ],
        'cache' => [
            'enabled' => env('LARAVEL_API_OPENAPI_CACHE_ENABLED', false),
            'store' => env('LARAVEL_API_OPENAPI_CACHE_STORE'),
            'ttl_seconds' => (int) env('LARAVEL_API_OPENAPI_CACHE_TTL_SECONDS', 300),
            'key_prefix' => env('LARAVEL_API_OPENAPI_CACHE_KEY_PREFIX', 'laravel_api_openapi'),
            // Bump this token when route/config changes need immediate cache invalidation.
            'bust_token' => env('LARAVEL_API_OPENAPI_CACHE_BUST_TOKEN', 'v1'),
        ],
        'docs_route' => [
            'enabled' => env('LARAVEL_API_OPENAPI_ROUTES_ENABLED', true),
            'json' => env('LARAVEL_API_OPENAPI_JSON_ROUTE', 'openapi.json'),
            'yaml' => env('LARAVEL_API_OPENAPI_YAML_ROUTE', 'openapi.yaml'),
        ],
        'docs_ui' => [
            'enabled' => env('LARAVEL_API_OPENAPI_UI_ENABLED', false),
            'driver' => env('LARAVEL_API_OPENAPI_UI_DRIVER', 'swagger'),
            'route' => env('LARAVEL_API_OPENAPI_UI_ROUTE', 'api-docs'),
            'title' => env('LARAVEL_API_OPENAPI_UI_TITLE', 'API Documentation'),
            'spec_url' => env('LARAVEL_API_OPENAPI_UI_SPEC_URL', '/openapi.json'),
            'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('LARAVEL_API_OPENAPI_UI_MIDDLEWARE', ''))))),
        ],
        'filters' => [
            'include_routes' => [],
            'exclude_routes' => [],
            'middleware' => [],
        ],
        'providers' => [
            // App\\OpenApi\\Providers\\DocumentSchemaProvider::class,
        ],
        'action_map' => [
            // App\\Http\\Controllers\\DocumentController@store' => [
            //     'requestBody' => [
            //         'required' => true,
            //         'content' => [
            //             'application/json' => [
            //                 'schema' => [
            //                     '$ref' => '#/components/schemas/CreateDocumentRequest',
            //                 ],
            //             ],
            //         ],
            //     ],
            //     'responses' => [
            //         '201' => [
            //             'description' => 'Created',
            //         ],
            //         '422' => [
            //             'description' => 'Validation error',
            //         ],
            //     ],
            // ],
        ],
        'overrides' => [
            'routes' => [
                // 'documents.store' => ['post' => ['summary' => 'Create document']],
            ],
            'actions' => [
                // 'App\\Http\\Controllers\\DocumentController@store' => ['post' => ['tags' => ['documents']]],
            ],
        ],
        'components' => [
            'schemas' => [
                // 'CreateDocumentRequest' => [
                //     'type' => 'object',
                //     'properties' => [
                //         'document_type' => ['type' => 'string'],
                //     ],
                // ],
            ],
        ],
    ],

    'response' => [
        'defaults' => [
            'success_message' => 'Success',
            'failed_message' => 'Request failed',
            'error_message' => 'Internal server error',
        ],
        'envelope' => [
            'ok_key' => 'ok',
            'type_key' => 'type',
            'message_key' => 'message',
            'data_key' => 'data',
            'meta_key' => 'meta',
            'errors_key' => 'errors',
        ],
    ],
];
