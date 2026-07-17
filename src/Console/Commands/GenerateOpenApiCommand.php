<?php

namespace Yoosuf\LaravelApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Yoosuf\LaravelApi\OpenApi\OpenApiGenerator;

class GenerateOpenApiCommand extends Command
{
    protected $signature = 'api:openapi
        {--format=all : Output format: json, yaml, or all}
        {--output= : Output file path. If omitted, package config paths are used}
        {--prefix= : Optional API path prefix override (for example: /api/v1)}
        {--include-route=* : Include only specific route names (repeatable or comma-separated)}
        {--exclude-route=* : Exclude route names (repeatable or comma-separated)}
        {--middleware=* : Include only routes containing these middleware names}';

    protected $description = 'Generate OpenAPI 3 documentation from Laravel routes';

    public function handle(OpenApiGenerator $generator, Filesystem $files): int
    {
        if (! (bool) config('laravel-api.openapi.enabled', true)) {
            $this->warn('OpenAPI generation is disabled by configuration.');

            return self::SUCCESS;
        }

        $format = strtolower((string) $this->option('format'));

        if (! in_array($format, ['json', 'yaml', 'all'], true)) {
            $this->error('Invalid --format value. Use json, yaml, or all.');

            return self::FAILURE;
        }

        $spec = $generator->generate($this->option('prefix'), [
            'include_routes' => $this->normalizeListOption((array) $this->option('include-route')),
            'exclude_routes' => $this->normalizeListOption((array) $this->option('exclude-route')),
            'middleware' => $this->normalizeListOption((array) $this->option('middleware')),
        ]);
        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            $written = $this->writeSingleOutput($files, $generator, $format, $output, $spec);

            if ($written === false) {
                return self::FAILURE;
            }

            $this->info('OpenAPI spec generated successfully.');

            return self::SUCCESS;
        }

        $targets = $this->resolveTargetsFromConfig($format);

        foreach ($targets as $targetFormat => $path) {
            $content = $targetFormat === 'json'
                ? $generator->toJson($spec)
                : $generator->toYaml($spec);

            $absolutePath = base_path($path);
            $files->ensureDirectoryExists(dirname($absolutePath));
            $files->put($absolutePath, $content);
            $this->line(sprintf('Generated %s: %s', strtoupper($targetFormat), $absolutePath));
        }

        $this->info('OpenAPI spec generated successfully.');

        return self::SUCCESS;
    }

    private function writeSingleOutput(Filesystem $files, OpenApiGenerator $generator, string $format, string $output, array $spec): bool
    {
        if ($format === 'all') {
            $this->error('When --output is provided, --format must be json or yaml.');

            return false;
        }

        $absolutePath = str_starts_with($output, '/') ? $output : base_path($output);
        $files->ensureDirectoryExists(dirname($absolutePath));

        $content = $format === 'json'
            ? $generator->toJson($spec)
            : $generator->toYaml($spec);

        $files->put($absolutePath, $content);
        $this->line(sprintf('Generated %s: %s', strtoupper($format), $absolutePath));

        return true;
    }

    private function resolveTargetsFromConfig(string $format): array
    {
        $jsonPath = (string) config('laravel-api.openapi.output.json_path', 'docs/openapi.generated.json');
        $yamlPath = (string) config('laravel-api.openapi.output.yaml_path', 'docs/openapi.generated.yaml');

        if ($format === 'json') {
            return ['json' => $jsonPath];
        }

        if ($format === 'yaml') {
            return ['yaml' => $yamlPath];
        }

        return [
            'json' => $jsonPath,
            'yaml' => $yamlPath,
        ];
    }

    /**
     * @param  array<int, string>  $raw
     * @return array<int, string>
     */
    private function normalizeListOption(array $raw): array
    {
        $values = [];

        foreach ($raw as $item) {
            foreach (explode(',', (string) $item) as $chunk) {
                $value = trim($chunk);

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }
}
