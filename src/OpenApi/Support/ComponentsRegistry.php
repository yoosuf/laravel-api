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
        if ($this->schemas === []) {
            return [];
        }

        ksort($this->schemas);

        return [
            'schemas' => $this->schemas,
        ];
    }
}
