<?php

namespace Yoosuf\LaravelApi\Tests\Unit;

use Illuminate\Support\Facades\Route;
use Yoosuf\LaravelApi\OpenApi\OpenApiGenerator;
use Yoosuf\LaravelApi\Tests\Fixtures\TestApiController;
use Yoosuf\LaravelApi\Tests\TestCase;

class OpenApiGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::get('/users', [TestApiController::class, 'index'])->name('users.index');
            Route::get('/users/{id}', [TestApiController::class, 'show'])->name('users.show')->whereNumber('id');
            Route::post('/users', [TestApiController::class, 'store'])->name('users.store');
        });
    }

    public function test_it_generates_paths_and_operations_for_routes(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $this->assertArrayHasKey('/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/users']);
        $this->assertArrayHasKey('post', $spec['paths']['/users']);
        $this->assertArrayHasKey('/users/{id}', $spec['paths']);
    }

    public function test_it_generates_json_and_yaml_outputs(): void
    {
        $generator = $this->app->make(OpenApiGenerator::class);
        $spec = $generator->generate('/api');

        $json = $generator->toJson($spec);
        $yaml = $generator->toYaml($spec);

        $this->assertStringContainsString('"openapi": "3.0.3"', $json);
        $this->assertStringContainsString('openapi: 3.0.3', $yaml);
    }

    public function test_it_infers_validation_and_resource_schema_fragments(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $postUsers = $spec['paths']['/users']['post'];

        $this->assertArrayHasKey('requestBody', $postUsers);
        $this->assertArrayHasKey('422', $postUsers['responses']);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('schemas', $spec['components']);
    }

    public function test_it_applies_route_name_filters(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api', [
            'include_routes' => ['users.show'],
            'exclude_routes' => [],
            'middleware' => ['api'],
        ]);

        $this->assertArrayHasKey('/users/{id}', $spec['paths']);
        $this->assertArrayNotHasKey('/users', $spec['paths']);
    }

    public function test_it_ignores_invalid_provider_classes_without_crashing(): void
    {
        config()->set('laravel-api.openapi.providers', ['App\\DoesNotExist\\Provider']);

        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $this->assertArrayHasKey('paths', $spec);
    }

    public function test_it_can_generate_using_cache_configuration(): void
    {
        config()->set('cache.default', 'array');
        config()->set('laravel-api.openapi.cache.enabled', true);
        config()->set('laravel-api.openapi.cache.store', 'array');
        config()->set('laravel-api.openapi.cache.ttl_seconds', 600);

        $generator = $this->app->make(OpenApiGenerator::class);
        $first = $generator->generate('/api');
        $second = $generator->generate('/api');

        $this->assertSame($first, $second);
        $this->assertArrayHasKey('/users', $second['paths']);
    }

    // -------------------------------------------------------------------------
    // Tags inference
    // -------------------------------------------------------------------------

    public function test_tags_are_inferred_from_controller_name(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        // TestApiController → tag 'test-api'
        $this->assertContains('test-api', $spec['paths']['/users']['get']['tags']);
        $this->assertContains('test-api', $spec['paths']['/users']['post']['tags']);
    }

    // -------------------------------------------------------------------------
    // Standard error response schemas in components
    // -------------------------------------------------------------------------

    public function test_standard_error_schemas_are_present_in_components(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('schemas', $spec['components']);
        $this->assertArrayHasKey('ErrorEnvelope', $spec['components']['schemas']);
        $this->assertArrayHasKey('StructuredError', $spec['components']['schemas']);
    }

    public function test_standard_response_references_are_present_in_components(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $responses = $spec['components']['responses'];
        $this->assertArrayHasKey('NotFound', $responses);
        $this->assertArrayHasKey('ValidationError', $responses);
        $this->assertArrayHasKey('TooManyRequests', $responses);
        $this->assertArrayHasKey('ServerError', $responses);
    }

    public function test_post_operation_references_422_response(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $postResponses = $spec['paths']['/users']['post']['responses'];
        $this->assertArrayHasKey('422', $postResponses);
    }

    public function test_all_operations_reference_500_and_429_responses(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $this->assertArrayHasKey('500', $operation['responses'], "Missing 500 on {$method} {$path}");
                $this->assertArrayHasKey('429', $operation['responses'], "Missing 429 on {$method} {$path}");
            }
        }
    }

    public function test_path_with_params_references_404_response(): void
    {
        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $getShowResponses = $spec['paths']['/users/{id}']['get']['responses'];
        $this->assertArrayHasKey('404', $getShowResponses);
    }

    // -------------------------------------------------------------------------
    // Auth middleware → security schemes
    // -------------------------------------------------------------------------

    public function test_auth_middleware_generates_bearer_security_scheme(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/protected', fn () => null)->name('protected.index');

        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $this->assertArrayHasKey('securitySchemes', $spec['components']);
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
        $this->assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
    }

    public function test_auth_gated_operation_includes_security_and_401_403(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/protected', fn () => null)->name('protected.show');

        $spec = $this->app->make(OpenApiGenerator::class)->generate('/api');

        $op = $spec['paths']['/protected']['get'];
        $this->assertArrayHasKey('security', $op);
        $this->assertArrayHasKey('401', $op['responses']);
        $this->assertArrayHasKey('403', $op['responses']);
    }
}
