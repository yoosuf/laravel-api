<?php

namespace Yoosuf\LaravelApi\OpenApi\Support;

class ComponentsRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $schemas = [];

    public function reset(): void
    {
        $this->schemas = [];
    }

    /**
     * @param  array<string, mixed>  $schemas
     */
    public function addSchemas(array $schemas): void
    {
        foreach ($schemas as $name => $schema) {
            if (! is_string($name) || $name === '' || ! is_array($schema)) {
                continue;
            }

            if (! isset($this->schemas[$name])) {
                $this->schemas[$name] = [];
            }

            $this->schemas[$name] = array_replace_recursive($this->schemas[$name], $schema);
        }
    }

    public function ensureSchema(string $name): void
    {
        if ($name === '' || isset($this->schemas[$name])) {
            return;
        }

        $this->schemas[$name] = [
            'type' => 'object',
            'additionalProperties' => true,
            'description' => 'Auto-generated schema. Override it with components.schemas or a schema provider for stricter typing.',
        ];
    }

    public function ensureSchemaByReference(string $reference): void
    {
        $prefix = '#/components/schemas/';

        if (! str_starts_with($reference, $prefix)) {
            return;
        }

        $name = substr($reference, strlen($prefix));

        if (! is_string($name) || $name === '') {
            return;
        }

        $this->ensureSchema($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function toOpenApiComponents(): array
    {
        $components = [];

        if ($this->schemas !== []) {
            ksort($this->schemas);
            $components['schemas'] = $this->schemas;
        }

        // Standard reusable response definitions referenced by operations.
        $components['responses'] = [
            'Unauthenticated' => [
                'description' => 'Authentication is required.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ],
            'Forbidden' => [
                'description' => 'Insufficient permissions.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ],
            'NotFound' => [
                'description' => 'Resource not found.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ],
            'ValidationError' => [
                'description' => 'Validation failed.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ],
            'TooManyRequests' => [
                'description' => 'Rate limit exceeded.',
                'headers' => [
                    'Retry-After' => ['schema' => ['type' => 'integer']],
                    'X-RateLimit-Limit' => ['schema' => ['type' => 'integer']],
                    'X-RateLimit-Reset' => ['schema' => ['type' => 'integer']],
                ],
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ],
            'ServerError' => [
                'description' => 'Internal server error.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ],
        ];

        return $components;
    }
}
