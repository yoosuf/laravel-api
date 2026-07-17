<?php

namespace Yoosuf\LaravelApi\OpenApi\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;

class ResourceSchemaMapper
{
    /**
     * @return array{operation: array<string, mixed>, schemas: array<string, array<string, mixed>>}
     */
    public function infer(Route $route, string $httpMethod): array
    {
        $result = ['operation' => [], 'schemas' => []];

        if (! in_array(strtolower($httpMethod), ['get', 'post', 'put', 'patch', 'delete'], true)) {
            return $result;
        }

        [$controllerClass, $controllerMethod] = $this->resolveControllerAction($route->getActionName());

        if ($controllerClass === null || $controllerMethod === null) {
            return $result;
        }

        if (! method_exists($controllerClass, $controllerMethod)) {
            return $result;
        }

        $reflection = new ReflectionMethod($controllerClass, $controllerMethod);
        $returnType = $reflection->getReturnType();

        if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return $result;
        }

        $returnClass = $returnType->getName();

        if (! class_exists($returnClass)) {
            return $result;
        }

        if (is_subclass_of($returnClass, 'Illuminate\\Http\\Resources\\Json\\ResourceCollection')) {
            $schemaName = $this->resourceCollectionSchemaName($returnClass);
            $itemSchemaName = $schemaName . 'Item';

            $result['schemas'][$itemSchemaName] = [
                'type' => 'object',
                'additionalProperties' => true,
            ];

            $result['schemas'][$schemaName] = [
                'type' => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/' . $itemSchemaName,
                ],
            ];

            $result['operation'] = [
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . $schemaName,
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            return $result;
        }

        if (! is_subclass_of($returnClass, 'Illuminate\\Http\\Resources\\Json\\JsonResource')) {
            return $result;
        }

        $schemaName = $this->resourceSchemaName($returnClass);

        $result['schemas'][$schemaName] = [
            'type' => 'object',
            'additionalProperties' => true,
        ];

        $result['operation'] = [
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $schemaName,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $result;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveControllerAction(string $actionName): array
    {
        if ($actionName === '' || $actionName === 'Closure' || ! str_contains($actionName, '@')) {
            return [null, null];
        }

        [$class, $method] = explode('@', $actionName, 2);

        if ($class === '' || $method === '') {
            return [null, null];
        }

        return [$class, $method];
    }

    private function resourceSchemaName(string $resourceClass): string
    {
        return Str::replace(' ', '', Str::headline(class_basename($resourceClass)));
    }

    private function resourceCollectionSchemaName(string $resourceClass): string
    {
        return $this->resourceSchemaName($resourceClass);
    }
}
