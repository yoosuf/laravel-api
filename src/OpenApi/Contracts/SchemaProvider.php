<?php

namespace Yoosuf\LaravelApi\OpenApi\Contracts;

interface SchemaProvider
{
    /**
     * Return OpenAPI schemas keyed by schema name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function schemas(): array;
}
