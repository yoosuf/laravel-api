<?php

namespace Yoosuf\LaravelApi;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Yoosuf\LaravelApi\Console\Commands\GenerateOpenApiCommand;
use Yoosuf\LaravelApi\Exceptions\ApiExceptionRenderer;
use Yoosuf\LaravelApi\Http\ApiResponder;
use Yoosuf\LaravelApi\Http\Middleware\ApiVersionMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\DeprecationMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\ForceJsonMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\RequestIdMiddleware;
use Yoosuf\LaravelApi\Http\Middleware\SecurityHeadersMiddleware;
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
        $this->app->singleton(ApiExceptionRenderer::class, fn ($app): ApiExceptionRenderer => new ApiExceptionRenderer($app->make(ApiResponder::class)));

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

        // Register named middleware aliases so applications can reference them
        // by short name in route groups or the Http Kernel middleware stack.
        $this->registerMiddlewareAliases();

        if ((bool) config('laravel-api.exceptions.auto_render', false)) {
            $this->app->make(ApiExceptionRenderer::class)->register();
        }

        if ((bool) config('laravel-api.openapi.docs_route.enabled', true) || (bool) config('laravel-api.health.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/laravel-api.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateOpenApiCommand::class,
            ]);
        }
    }

    private function registerMiddlewareAliases(): void
    {
        if (! $this->app->bound('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');

        $router->aliasMiddleware('laravel-api.request-id', RequestIdMiddleware::class);
        $router->aliasMiddleware('laravel-api.versioning', ApiVersionMiddleware::class);
        $router->aliasMiddleware('laravel-api.force-json', ForceJsonMiddleware::class);
        $router->aliasMiddleware('laravel-api.security-headers', SecurityHeadersMiddleware::class);
        $router->aliasMiddleware('laravel-api.deprecation', DeprecationMiddleware::class);
    }
}
