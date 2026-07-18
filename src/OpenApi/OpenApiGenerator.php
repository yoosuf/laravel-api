<?php

namespace Yoosuf\LaravelApi\OpenApi;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Yoosuf\LaravelApi\OpenApi\Contracts\OperationOverrideProvider;
use Yoosuf\LaravelApi\OpenApi\Contracts\SchemaProvider;
use Yoosuf\LaravelApi\OpenApi\Support\ComponentsRegistry;
use Yoosuf\LaravelApi\OpenApi\Support\ResourceSchemaMapper;
use Yoosuf\LaravelApi\OpenApi\Support\ValidationRuleMapper;

class OpenApiGenerator
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
        private readonly ComponentsRegistry $componentsRegistry,
        private readonly ResourceSchemaMapper $resourceSchemaMapper,
        private readonly ValidationRuleMapper $validationRuleMapper,
        private readonly ?CacheFactory $cache = null
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function generate(?string $forcedPrefix = null, array $filters = []): array
    {
        $pathPrefix = $forcedPrefix ?? (string) config('laravel-api.openapi.default_path_prefix', '/api');
        $filters = $this->normalizeFilters($filters);

        if ($this->shouldUseCache()) {
            $store = (string) config('laravel-api.openapi.cache.store', '');
            $ttl = max(1, (int) config('laravel-api.openapi.cache.ttl_seconds', 300));
            $cacheKey = $this->cacheKey($pathPrefix, $filters);
            $repository = $store !== '' ? $this->cache?->store($store) : $this->cache?->store();

            if ($repository !== null) {
                /** @var array<string, mixed> $cachedSpec */
                $cachedSpec = $repository->remember($cacheKey, $ttl, fn (): array => $this->buildSpec($pathPrefix, $filters));

                return $cachedSpec;
            }
        }

        return $this->buildSpec($pathPrefix, $filters);
    }

    /**
     * @param  array{include_routes: array<int, string>, exclude_routes: array<int, string>, middleware: array<int, string>}  $filters
     * @return array<string, mixed>
     */
    private function buildSpec(string $pathPrefix, array $filters): array
    {
        $this->componentsRegistry->reset();
        $this->componentsRegistry->addSchemas((array) config('laravel-api.openapi.components.schemas', []));
        $this->componentsRegistry->addSchemas($this->standardErrorSchemas());

        [$providerSchemas, $providerOverrides] = $this->loadProviders();
        $this->componentsRegistry->addSchemas($providerSchemas);

        $overrides = $this->mergeOverrides(
            (array) config('laravel-api.openapi.overrides', []),
            $providerOverrides
        );

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => (string) config('laravel-api.openapi.title', config('app.name', 'Laravel API')),
                'version' => (string) config('laravel-api.openapi.version', '1.0.0'),
                'description' => (string) config('laravel-api.openapi.description', ''),
            ],
            'servers' => [
                ['url' => (string) config('laravel-api.openapi.server_url', rtrim((string) config('app.url', 'http://localhost'), '/') . '/api')],
            ],
            'paths' => [],
        ];

        $routeCollection = $this->router->getRoutes();
        $routes = method_exists($routeCollection, 'getRoutes') ? $routeCollection->getRoutes() : [];

        $usesAuth = false;

        foreach ($routes as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $uri = '/' . ltrim($route->uri(), '/');

            if (! $this->shouldIncludeRoute($route, $uri, $pathPrefix, $filters)) {
                continue;
            }

            $path = $this->normalizePath($uri, $pathPrefix);
            $operations = $this->buildOperations($route, $overrides);

            if ($operations === []) {
                continue;
            }

            if (! isset($spec['paths'][$path])) {
                $spec['paths'][$path] = [];
            }

            foreach ($operations as $method => $operation) {
                $spec['paths'][$path][$method] = $operation;
            }

            if ($this->routeUsesAuthMiddleware($route)) {
                $usesAuth = true;
            }
        }

        $components = $this->componentsRegistry->toOpenApiComponents();

        // Add bearer security scheme when at least one route is auth-gated.
        if ($usesAuth) {
            $components['securitySchemes'] = [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'token',
                ],
            ];
        }

        if ($components !== []) {
            $spec['components'] = $components;
        }

        ksort($spec['paths']);

        return $spec;
    }

    private function shouldUseCache(): bool
    {
        return (bool) config('laravel-api.openapi.cache.enabled', false) && $this->cache !== null;
    }

    /**
     * @param  array{include_routes: array<int, string>, exclude_routes: array<int, string>, middleware: array<int, string>}  $filters
     */
    private function cacheKey(string $pathPrefix, array $filters): string
    {
        $payload = [
            'prefix' => $pathPrefix,
            'filters' => $filters,
            'bust' => (string) config('laravel-api.openapi.cache.bust_token', 'v1'),
            'app' => (string) config('app.name', 'laravel-api'),
        ];

        return sprintf(
            '%s:%s',
            (string) config('laravel-api.openapi.cache.key_prefix', 'laravel_api_openapi'),
            md5((string) json_encode($payload))
        );
    }

    public function toJson(array $spec): string
    {
        return (string) json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function toYaml(array $spec): string
    {
        return rtrim($this->yamlify($spec)) . "\n";
    }

    private function buildOperations(Route $route, array $overrides): array
    {
        $operations = [];
        $methods = array_values(array_filter($route->methods(), static fn (string $m): bool => ! in_array($m, ['HEAD', 'OPTIONS'], true)));
        $actionName = $route->getActionName();
        $summary = $this->summaryFromAction($actionName);
        $tags = $this->inferTagsFromAction($actionName);
        $routeUsesAuth = $this->routeUsesAuthMiddleware($route);
        $hasPathParams = $route->parameterNames() !== [];

        foreach ($methods as $method) {
            $httpMethod = strtolower($method);

            $operations[$httpMethod] = [
                'summary' => $summary,
                'operationId' => $this->operationId($httpMethod, $route),
                'responses' => [
                    '200' => ['description' => 'Successful response'],
                ],
            ];

            if ($tags !== []) {
                $operations[$httpMethod]['tags'] = $tags;
            }

            if ($routeUsesAuth) {
                $operations[$httpMethod]['security'] = [['bearerAuth' => []]];
                $operations[$httpMethod]['responses']['401'] = ['$ref' => '#/components/responses/Unauthenticated'];
                $operations[$httpMethod]['responses']['403'] = ['$ref' => '#/components/responses/Forbidden'];
            }

            if (in_array($httpMethod, ['post', 'put', 'patch'], true)) {
                $operations[$httpMethod]['responses']['422'] = ['$ref' => '#/components/responses/ValidationError'];
            }

            if ($hasPathParams) {
                $operations[$httpMethod]['responses']['404'] = ['$ref' => '#/components/responses/NotFound'];
            }

            $operations[$httpMethod]['responses']['500'] = ['$ref' => '#/components/responses/ServerError'];
            $operations[$httpMethod]['responses']['429'] = ['$ref' => '#/components/responses/TooManyRequests'];

            $parameters = $this->extractPathParameters($route);

            if ($parameters !== []) {
                $operations[$httpMethod]['parameters'] = $parameters;
            }

            $validationFragment = $this->validationRuleMapper->inferRequestBody($route, $httpMethod);

            if ($validationFragment !== []) {
                $operations[$httpMethod] = $this->mergeOperation($operations[$httpMethod], $validationFragment);
            }

            $operations[$httpMethod] = $this->applyActionMap($route, $httpMethod, $operations[$httpMethod]);

            $resourceInference = $this->resourceSchemaMapper->infer($route, $httpMethod);

            if ($resourceInference['schemas'] !== []) {
                $this->componentsRegistry->addSchemas($resourceInference['schemas']);
            }

            if ($resourceInference['operation'] !== []) {
                $operations[$httpMethod] = $this->mergeOperation($operations[$httpMethod], $resourceInference['operation']);
            }

            $operations[$httpMethod] = $this->applyOverrides($route, $httpMethod, $operations[$httpMethod], $overrides);
            $this->registerReferencedSchemas($operations[$httpMethod]);
        }

        return $operations;
    }

    private function applyActionMap(Route $route, string $httpMethod, array $operation): array
    {
        $actionMap = (array) config('laravel-api.openapi.action_map', []);
        $action = $route->getActionName();

        if (! is_string($action) || $action === '' || ! isset($actionMap[$action]) || ! is_array($actionMap[$action])) {
            return $operation;
        }

        $fragment = $this->fragmentForMethod($actionMap[$action], $httpMethod);

        if ($fragment === []) {
            return $operation;
        }

        return $this->mergeOperation($operation, $fragment);
    }

    private function applyOverrides(Route $route, string $httpMethod, array $operation, array $overrides): array
    {
        $fragments = [];
        $routeName = $route->getName();

        if (is_string($routeName) && $routeName !== '' && isset($overrides['routes'][$routeName]) && is_array($overrides['routes'][$routeName])) {
            $fragments[] = $this->fragmentForMethod($overrides['routes'][$routeName], $httpMethod);
        }

        $actionName = $route->getActionName();

        if (is_string($actionName) && $actionName !== '' && isset($overrides['actions'][$actionName]) && is_array($overrides['actions'][$actionName])) {
            $fragments[] = $this->fragmentForMethod($overrides['actions'][$actionName], $httpMethod);
        }

        foreach ($fragments as $fragment) {
            if ($fragment === []) {
                continue;
            }

            $operation = $this->mergeOperation($operation, $fragment);
        }

        return $operation;
    }

    private function fragmentForMethod(array $entry, string $httpMethod): array
    {
        $methodKey = strtolower($httpMethod);
        $fragment = [];

        if (isset($entry['*']) && is_array($entry['*'])) {
            $fragment = array_replace_recursive($fragment, $entry['*']);
        }

        $methodScoped = $entry[$methodKey] ?? null;

        if (is_array($methodScoped)) {
            $fragment = array_replace_recursive($fragment, $methodScoped);
        }

        $reservedKeys = ['*', 'get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

        foreach ($entry as $key => $value) {
            if (in_array((string) $key, $reservedKeys, true)) {
                continue;
            }

            $fragment[$key] = $value;
        }

        return $fragment;
    }

    private function mergeOperation(array $operation, array $fragment): array
    {
        return array_replace_recursive($operation, $fragment);
    }

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function loadProviders(): array
    {
        $schemas = [];
        $overrides = ['routes' => [], 'actions' => []];
        $providers = (array) config('laravel-api.openapi.providers', []);

        foreach ($providers as $providerClass) {
            if (! is_string($providerClass) || $providerClass === '') {
                continue;
            }

            try {
                $provider = $this->container->make($providerClass);
            } catch (\Throwable) {
                continue;
            }

            if ($provider instanceof SchemaProvider) {
                $schemas = array_replace_recursive($schemas, $provider->schemas());
            }

            if ($provider instanceof OperationOverrideProvider) {
                $overrides = $this->mergeOverrides($overrides, $provider->overrides());
            }
        }

        return [$schemas, $overrides];
    }

    private function mergeOverrides(array $base, array $additional): array
    {
        $base['routes'] = isset($base['routes']) && is_array($base['routes']) ? $base['routes'] : [];
        $base['actions'] = isset($base['actions']) && is_array($base['actions']) ? $base['actions'] : [];

        $routes = isset($additional['routes']) && is_array($additional['routes']) ? $additional['routes'] : [];
        $actions = isset($additional['actions']) && is_array($additional['actions']) ? $additional['actions'] : [];

        $base['routes'] = array_replace_recursive($base['routes'], $routes);
        $base['actions'] = array_replace_recursive($base['actions'], $actions);

        return $base;
    }

    private function registerReferencedSchemas(mixed $data): void
    {
        if (! is_array($data)) {
            return;
        }

        if (isset($data['$ref']) && is_string($data['$ref'])) {
            $this->componentsRegistry->ensureSchemaByReference($data['$ref']);
        }

        foreach ($data as $value) {
            $this->registerReferencedSchemas($value);
        }
    }

    private function extractPathParameters(Route $route): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $pattern = $route->wheres[$name] ?? null;
            $schema = ['type' => 'string'];

            if (is_string($pattern) && $pattern !== '') {
                $schema['pattern'] = $pattern;
            }

            $parameters[] = [
                'in' => 'path',
                'name' => $name,
                'required' => true,
                'schema' => $schema,
            ];
        }

        return $parameters;
    }

    private function shouldIncludePath(string $uri, string $prefix): bool
    {
        if ($prefix === '' || $prefix === '/') {
            return true;
        }

        $normalizedPrefix = '/' . trim($prefix, '/');

        return str_starts_with($uri, $normalizedPrefix);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function shouldIncludeRoute(Route $route, string $uri, string $prefix, array $filters): bool
    {
        if (! $this->shouldIncludePath($uri, $prefix)) {
            return false;
        }

        $routeName = $route->getName();

        if ($filters['include_routes'] !== []) {
            if (! is_string($routeName) || ! in_array($routeName, $filters['include_routes'], true)) {
                return false;
            }
        }

        if (is_string($routeName) && in_array($routeName, $filters['exclude_routes'], true)) {
            return false;
        }

        if ($filters['middleware'] !== []) {
            $routeMiddleware = array_map(static fn (mixed $middleware): string => (string) $middleware, $route->gatherMiddleware());

            foreach ($filters['middleware'] as $requiredMiddleware) {
                if (! in_array($requiredMiddleware, $routeMiddleware, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{include_routes: array<int, string>, exclude_routes: array<int, string>, middleware: array<int, string>}
     */
    private function normalizeFilters(array $filters): array
    {
        $configFilters = (array) config('laravel-api.openapi.filters', []);

        $includeRoutes = $this->normalizeFilterValues($filters['include_routes'] ?? $configFilters['include_routes'] ?? []);
        $excludeRoutes = $this->normalizeFilterValues($filters['exclude_routes'] ?? $configFilters['exclude_routes'] ?? []);
        $middleware = $this->normalizeFilterValues($filters['middleware'] ?? $configFilters['middleware'] ?? []);

        return [
            'include_routes' => $includeRoutes,
            'exclude_routes' => $excludeRoutes,
            'middleware' => $middleware,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeFilterValues(mixed $value): array
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        $items = [];

        foreach ($value as $entry) {
            foreach (explode(',', (string) $entry) as $chunk) {
                $item = trim($chunk);

                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function normalizePath(string $uri, string $prefix): string
    {
        if ($prefix === '' || $prefix === '/') {
            return $this->asOpenApiPath($uri);
        }

        $normalizedPrefix = '/' . trim($prefix, '/');

        if (! str_starts_with($uri, $normalizedPrefix)) {
            return $this->asOpenApiPath($uri);
        }

        $trimmed = substr($uri, strlen($normalizedPrefix));

        return $this->asOpenApiPath($trimmed === '' ? '/' : $trimmed);
    }

    private function asOpenApiPath(string $uri): string
    {
        $path = '/' . trim($uri, '/');
        $path = preg_replace('/\{([^}]+)\??\}/', '{$1}', $path) ?? $path;

        return $path === '//' ? '/' : $path;
    }

    private function summaryFromAction(string $actionName): string
    {
        if ($actionName === 'Closure' || $actionName === '') {
            return 'Route operation';
        }

        $parts = explode('@', $actionName);
        $method = $parts[1] ?? $actionName;

        return ucfirst(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $method)));
    }

    /**
     * Infer OpenAPI tags from the controller class name.
     *
     * OrderController → [orders], UserProfileController → [user-profiles]
     *
     * @return array<int, string>
     */
    private function inferTagsFromAction(string $actionName): array
    {
        if ($actionName === 'Closure' || $actionName === '' || ! str_contains($actionName, '@')) {
            return [];
        }

        [$class] = explode('@', $actionName, 2);
        $shortName = class_basename($class);
        $tag = (string) preg_replace('/Controller$/', '', $shortName);

        if ($tag === '') {
            return [];
        }

        // CamelCase → kebab-case, e.g. UserProfile → user-profile
        $tag = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $tag));

        return [$tag];
    }

    /**
     * Returns true when the route carries any auth-related middleware.
     */
    private function routeUsesAuthMiddleware(Route $route): bool
    {
        $authMiddleware = ['auth', 'auth:api', 'auth:sanctum', 'auth:passport'];

        foreach ($route->gatherMiddleware() as $m) {
            $name = is_string($m) ? explode(':', $m)[0] : '';

            if (in_array($name, $authMiddleware, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Standard reusable response schema definitions added to every generated spec.
     *
     * @return array<string, array<string, mixed>>
     */
    private function standardErrorSchemas(): array
    {
        return [
            'ErrorEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'ok' => ['type' => 'boolean', 'example' => false],
                    'type' => ['type' => 'string',  'example' => 'error'],
                    'message' => ['type' => 'string'],
                    'data' => ['nullable' => true],
                    'errors' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'StructuredError' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'target' => ['type' => 'string', 'nullable' => true],
                            'details' => ['type' => 'array',  'items' => ['type' => 'object']],
                            'innererror' => ['type' => 'object',  'nullable' => true, 'additionalProperties' => true],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function operationId(string $method, Route $route): string
    {
        $name = $route->getName();

        if (is_string($name) && $name !== '') {
            return strtolower($method) . '_' . str_replace('.', '_', $name);
        }

        return strtolower($method) . '_' . md5($route->uri() . $method);
    }

    private function yamlify(mixed $data, int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);

        if (! is_array($data)) {
            return $this->yamlScalar($data);
        }

        if ($data === []) {
            return '[]';
        }

        $isList = array_keys($data) === range(0, count($data) - 1);
        $lines = [];

        foreach ($data as $key => $value) {
            if ($isList) {
                if (is_array($value) && $value !== []) {
                    $lines[] = $indent . '- ' . (array_keys($value) === range(0, count($value) - 1) ? '' : '');
                    $lines[] = $this->indentBlock($this->yamlify($value, $depth + 1), 0);
                } else {
                    $lines[] = $indent . '- ' . $this->yamlify($value, $depth + 1);
                }

                continue;
            }

            $safeKey = (string) $key;

            if (is_array($value) && $value !== []) {
                $lines[] = $indent . $safeKey . ':';
                $lines[] = $this->yamlify($value, $depth + 1);
            } else {
                $lines[] = $indent . $safeKey . ': ' . $this->yamlify($value, $depth + 1);
            }
        }

        return implode("\n", $lines);
    }

    private function yamlScalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = str_replace("\n", '\\n', (string) $value);

        if ($string === '' || preg_match('/[:\-\{\}\[\],&\*#\?\|<>!=%@`]/', $string) === 1 || str_contains($string, ' ')) {
            $escaped = str_replace('"', '\\"', $string);

            return '"' . $escaped . '"';
        }

        return $string;
    }

    private function indentBlock(string $block, int $depth): string
    {
        if ($block === '') {
            return '';
        }

        $indent = str_repeat('  ', $depth);

        return implode("\n", array_map(static fn (string $line): string => $indent . $line, explode("\n", $block)));
    }
}
