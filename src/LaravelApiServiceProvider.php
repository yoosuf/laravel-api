<?php

namespace Yoosuf\LaravelApi;

use Illuminate\Support\ServiceProvider;
use Yoosuf\LaravelApi\Console\Commands\GenerateOpenApiCommand;
use Yoosuf\LaravelApi\Http\ApiResponder;
use Yoosuf\LaravelApi\OpenApi\OpenApiGenerator;
use Yoosuf\LaravelApi\OpenApi\Support\ComponentsRegistry;
use Yoosuf\LaravelApi\OpenApi\Support\ResourceSchemaMapper;
use Yoosuf\LaravelApi\OpenApi\Support\ValidationRuleMapper;

class LaravelApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-api.php', 'laravel-api');

        $this->app->singleton(ComponentsRegistry::class, static fn (): ComponentsRegistry => new ComponentsRegistry);
        $this->app->singleton(ResourceSchemaMapper::class, static fn (): ResourceSchemaMapper => new ResourceSchemaMapper);
        $this->app->singleton(ValidationRuleMapper::class, static fn (): ValidationRuleMapper => new ValidationRuleMapper);
        $this->app->singleton(ApiResponder::class, static fn (): ApiResponder => new ApiResponder);
        $this->app->alias(ApiResponder::class, 'laravel-api.response');

        $this->app->singleton(OpenApiGenerator::class, function ($app) {
            return new OpenApiGenerator(
                $app['router'],
                $app,
                $app->make(ComponentsRegistry::class),
                $app->make(ResourceSchemaMapper::class),
                $app->make(ValidationRuleMapper::class),
                $app->bound('cache') ? $app['cache'] : null
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-api');

        $this->publishes([
            __DIR__ . '/../config/laravel-api.php' => config_path('laravel-api.php'),
        ], 'laravel-api-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/laravel-api'),
        ], 'laravel-api-views');

        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/laravel-api'),
        ], 'laravel-api-assets');

        if ((bool) config('laravel-api.openapi.docs_route.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/laravel-api.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateOpenApiCommand::class,
            ]);
        }
    }
}
