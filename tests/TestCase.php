<?php

namespace Yoosuf\LaravelApi\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Yoosuf\LaravelApi\LaravelApiServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelApiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('laravel-api.openapi.enabled', true);
        $app['config']->set('laravel-api.openapi.default_path_prefix', '/api');
        $app['config']->set('laravel-api.openapi.docs_route.enabled', false);
        $app['config']->set('laravel-api.openapi.docs_ui.enabled', false);
    }
}
