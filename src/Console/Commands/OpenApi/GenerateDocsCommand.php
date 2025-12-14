<?php

declare(strict_types=1);

namespace Luminor\Console\Commands\OpenApi;

use Luminor\Console\Command;
use Luminor\Http\OpenApi\OpenApiGenerator;

/**
 * Generate OpenAPI documentation.
 */
final class GenerateDocsCommand extends Command
{
    protected string $signature = 'openapi:generate
                                    {--format=json : Output format (json or yaml)}
                                    {--output= : Output file path}';

    protected string $description = 'Generate OpenAPI documentation';

    public function handle(): int
    {
        $format = $this->option('format');
        $output = $this->option('output');

        $this->info('Generating OpenAPI documentation...');

        // Create generator
        $generator = new OpenApiGenerator(
            title: config('app.name', 'API'),
            version: config('app.version', '1.0.0'),
            description: config('app.description', '')
        );

        // Add servers
        $generator->addServer(
            config('app.url', 'http://localhost'),
            'API Server'
        );

        // Add common security schemes
        $generator->addSecurityScheme('bearerAuth', [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        // TODO: Auto-discover routes and controllers
        // This would scan controllers for OpenAPI attributes

        // Generate output
        $content = $format === 'yaml' ? $generator->toYaml() : $generator->toJson();

        if ($output) {
            file_put_contents($output, $content);
            $this->info("Documentation generated: {$output}");
        } else {
            $this->line($content);
        }

        return self::SUCCESS;
    }
}
