<?php

namespace Yoosuf\LaravelApi\OpenApi\Contracts;

interface OperationOverrideProvider
{
    /**
     * Return operation overrides grouped by route name and action.
     *
     * Expected shape:
     * [
     *   'routes' => ['route.name' => [...]],
     *   'actions' => ['App\\Http\\Controllers\\ExampleController@store' => [...]],
     * ]
     *
     * @return array<string, mixed>
     */
    public function overrides(): array;
}
