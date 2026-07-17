<?php

namespace Yoosuf\LaravelApi\Tests\Integration;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Yoosuf\LaravelApi\Tests\Fixtures\TestApiController;
use Yoosuf\LaravelApi\Tests\TestCase;

class OpenApiCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::get('/users', [TestApiController::class, 'index'])->name('users.index');
            Route::post('/users', [TestApiController::class, 'store'])->name('users.store');
        });
    }

    public function test_openapi_command_generates_json_and_yaml_files(): void
    {
        $filesystem = $this->app->make(Filesystem::class);
        $jsonPath = base_path('docs/openapi.generated.json');
        $yamlPath = base_path('docs/openapi.generated.yaml');

        if ($filesystem->exists($jsonPath)) {
            $filesystem->delete($jsonPath);
        }

        if ($filesystem->exists($yamlPath)) {
            $filesystem->delete($yamlPath);
        }

        $exitCode = Artisan::call('api:openapi', ['--format' => 'all']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($filesystem->exists($jsonPath));
        $this->assertTrue($filesystem->exists($yamlPath));

        $json = (string) $filesystem->get($jsonPath);
        $yaml = (string) $filesystem->get($yamlPath);

        $this->assertStringContainsString('"openapi": "3.0.3"', $json);
        $this->assertStringContainsString('openapi: 3.0.3', $yaml);
    }

    public function test_openapi_command_can_filter_by_route_name(): void
    {
        $jsonPath = base_path('docs/openapi.generated.json');

        $exitCode = Artisan::call('api:openapi', [
            '--format' => 'json',
            '--include-route' => ['users.index'],
        ]);

        $this->assertSame(0, $exitCode);

        $decoded = json_decode((string) file_get_contents($jsonPath), true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('/users', $decoded['paths']);
        $this->assertArrayNotHasKey('/users/{id}', $decoded['paths']);
    }
}
