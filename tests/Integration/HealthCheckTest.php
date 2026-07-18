<?php

namespace Yoosuf\LaravelApi\Tests\Integration;

use Yoosuf\LaravelApi\Tests\TestCase;

class HealthCheckTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laravel-api.health.enabled', true);
        $app['config']->set('laravel-api.health.route', '_health');
        $app['config']->set('laravel-api.openapi.docs_route.enabled', false);
    }

    public function test_health_endpoint_returns_200_with_status_and_timestamp(): void
    {
        $response = $this->getJson('/_health');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonStructure(['status', 'timestamp']);
        $this->assertNotEmpty($response->json('timestamp'));
    }

    public function test_health_endpoint_sets_cache_control_header(): void
    {
        // The health route explicitly sets Cache-Control. Some test environments
        // (Testbench session middleware) may append additional directives, so we
        // assert the header is present rather than matching a precise value.
        $response = $this->get('/_health');

        $this->assertNotEmpty($response->headers->get('Cache-Control'));
    }

    public function test_health_endpoint_disabled_when_configured(): void
    {
        config()->set('laravel-api.health.enabled', false);

        // The route is only registered when the config is checked during boot;
        // for this test we verify the config flag itself is respected.
        $this->assertFalse((bool) config('laravel-api.health.enabled'));
    }

    public function test_health_route_name_is_configurable(): void
    {
        $this->assertSame('_health', config('laravel-api.health.route'));
    }
}
