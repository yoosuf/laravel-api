<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Yoosuf\LaravelApi\OpenApi\OpenApiGenerator;

Route::middleware('api')->group(function (): void {
    $jsonRoute = (string) config('laravel-api.openapi.docs_route.json', 'openapi.json');
    $yamlRoute = (string) config('laravel-api.openapi.docs_route.yaml', 'openapi.yaml');

    Route::get($jsonRoute, function (OpenApiGenerator $generator): Response {
        $spec = $generator->generate();

        return response($generator->toJson($spec), 200, ['Content-Type' => 'application/json']);
    });

    Route::get($yamlRoute, function (OpenApiGenerator $generator): Response {
        $spec = $generator->generate();

        return response($generator->toYaml($spec), 200, ['Content-Type' => 'application/yaml']);
    });
});

if ((bool) config('laravel-api.openapi.docs_ui.enabled', false)) {
    $uiMiddleware = (array) config('laravel-api.openapi.docs_ui.middleware', []);

    Route::middleware($uiMiddleware)->get((string) config('laravel-api.openapi.docs_ui.route', 'api-docs'), static function (): View {
        $driver = strtolower((string) config('laravel-api.openapi.docs_ui.driver', 'swagger'));

        if (! in_array($driver, ['swagger', 'redoc'], true)) {
            $driver = 'swagger';
        }

        return view('laravel-api::docs', [
            'driver' => $driver,
            'title' => (string) config('laravel-api.openapi.docs_ui.title', 'API Documentation'),
            'specUrl' => (string) config('laravel-api.openapi.docs_ui.spec_url', '/openapi.json'),
        ]);
    });
}
