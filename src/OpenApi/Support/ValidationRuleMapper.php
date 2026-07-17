<?php

namespace Yoosuf\LaravelApi\OpenApi\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use ReflectionMethod;
use ReflectionNamedType;

class ValidationRuleMapper
{
    /**
     * @return array<string, mixed>
     */
    public function inferRequestBody(Route $route, string $httpMethod): array
    {
        if (! in_array(strtoupper($httpMethod), ['POST', 'PUT', 'PATCH'], true)) {
            return [];
        }

        [$controllerClass, $controllerMethod] = $this->resolveControllerAction($route->getActionName());

        if ($controllerClass === null || $controllerMethod === null || ! method_exists($controllerClass, $controllerMethod)) {
            return [];
        }

        $reflection = new ReflectionMethod($controllerClass, $controllerMethod);
        $rules = $this->extractFormRequestRules($reflection);

        if ($rules === []) {
            return [];
        }

        $schema = $this->mapRulesToSchema($rules);

        return [
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => $schema,
                    ],
                ],
            ],
            'responses' => [
                '422' => [
                    'description' => 'Validation error',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function extractFormRequestRules(ReflectionMethod $reflection): array
    {
        foreach ($reflection->getParameters() as $parameter) {
            $parameterType = $parameter->getType();

            if (! $parameterType instanceof ReflectionNamedType || $parameterType->isBuiltin()) {
                continue;
            }

            $parameterClass = $parameterType->getName();

            if (! is_subclass_of($parameterClass, FormRequest::class)) {
                continue;
            }

            try {
                $formRequest = new $parameterClass;
                $rawRules = $this->invokeRules($formRequest);
            } catch (\Throwable) {
                return [];
            }

            $normalized = [];

            foreach ((array) $rawRules as $field => $rules) {
                if (! is_string($field) || $field === '') {
                    continue;
                }

                if (is_string($rules)) {
                    $normalized[$field] = array_values(array_filter(explode('|', $rules), static fn (string $rule): bool => $rule !== ''));

                    continue;
                }

                if (is_array($rules)) {
                    $normalized[$field] = array_values(array_filter(array_map(static function (mixed $rule): string {
                        if (is_string($rule)) {
                            return $rule;
                        }

                        if (is_object($rule) && method_exists($rule, '__toString')) {
                            return (string) $rule;
                        }

                        return '';
                    }, $rules), static fn (string $rule): bool => $rule !== ''));
                }
            }

            return $normalized;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeRules(object $formRequest): array
    {
        if (! method_exists($formRequest, 'rules')) {
            return [];
        }

        $rules = $formRequest->rules();

        return is_array($rules) ? $rules : [];
    }

    /**
     * @param  array<string, array<int, string>>  $rulesByField
     * @return array<string, mixed>
     */
    private function mapRulesToSchema(array $rulesByField): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        $required = [];

        foreach ($rulesByField as $field => $rules) {
            $property = ['type' => 'string'];

            foreach ($rules as $rule) {
                $ruleName = strtolower((string) strtok($rule, ':'));
                $ruleArgs = explode(',', (string) strstr($rule, ':'));
                $ruleArgs = array_values(array_filter(array_map(static fn (string $value): string => trim($value, ': '), $ruleArgs), static fn (string $value): bool => $value !== ''));
                $propertyType = (string) $property['type'];

                if ($ruleName === 'required') {
                    $required[] = $field;
                }

                if ($ruleName === 'string') {
                    $property['type'] = 'string';
                }

                if ($ruleName === 'numeric') {
                    $property['type'] = 'number';
                }

                if ($ruleName === 'integer') {
                    $property['type'] = 'integer';
                }

                if ($ruleName === 'array') {
                    $property['type'] = 'array';
                    $property['items'] = ['type' => 'string'];
                }

                if ($ruleName === 'email') {
                    $property['type'] = 'string';
                    $property['format'] = 'email';
                }

                if ($ruleName === 'min' && isset($ruleArgs[0])) {
                    if ($propertyType === 'string') {
                        $property['minLength'] = (int) $ruleArgs[0];
                    }

                    if (in_array($propertyType, ['number', 'integer'], true)) {
                        $property['minimum'] = (float) $ruleArgs[0];
                    }

                    if ($propertyType === 'array') {
                        $property['minItems'] = (int) $ruleArgs[0];
                    }
                }

                if ($ruleName === 'max' && isset($ruleArgs[0])) {
                    if ($propertyType === 'string') {
                        $property['maxLength'] = (int) $ruleArgs[0];
                    }

                    if (in_array($propertyType, ['number', 'integer'], true)) {
                        $property['maximum'] = (float) $ruleArgs[0];
                    }

                    if ($propertyType === 'array') {
                        $property['maxItems'] = (int) $ruleArgs[0];
                    }
                }

                if ($ruleName === 'in' && $ruleArgs !== []) {
                    $property['enum'] = $ruleArgs;
                }
            }

            $schema['properties'][$field] = $property;
        }

        if ($required !== []) {
            $schema['required'] = array_values(array_unique($required));
        }

        return $schema;
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
}
