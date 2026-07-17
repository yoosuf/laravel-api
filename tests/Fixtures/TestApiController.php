<?php

namespace Yoosuf\LaravelApi\Tests\Fixtures;

class TestApiController
{
    public function index(): UsersCollectionResource
    {
        return new UsersCollectionResource([]);
    }

    public function show(): UserResource
    {
        return new UserResource(['id' => 1, 'name' => 'A']);
    }

    public function store(TestFormRequest $request): array
    {
        return ['ok' => true];
    }
}
