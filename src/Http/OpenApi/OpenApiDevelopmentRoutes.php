<?php

declare(strict_types=1);

namespace Luminor\Http\OpenApi;

use Luminor\Http\HttpKernel;
use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Registers OpenAPI/Swagger UI routes for development mode.
 *
 * When enabled, this will:
 * - Register "/" as the Swagger UI index (API documentation)
 * - Register "/api/openapi.json" to serve the OpenAPI specification
 *
 * This is inspired by Laravel Scramble and provides automatic API documentation
 * in development environments.
 */
final class OpenApiDevelopmentRoutes
{
    private const SWAGGER_UI_VERSION = '5.9.0';

    public function __construct(
        private readonly HttpKernel $kernel,
        private readonly OpenApiGenerator $generator,
        private readonly string $title = 'API Documentation',
        private readonly string $specPath = '/api/openapi.json'
    ) {}

    /**
     * Register development routes for API documentation.
     */
    public function register(): void
    {
        // Register the OpenAPI JSON endpoint
        $this->kernel->get($this->specPath, function (Request $request, Response $response): void {
            $response->setHeader('Content-Type', 'application/json');
            $response->setHeader('Access-Control-Allow-Origin', '*');
            $response->setContent($this->generator->toJson());
        });

        // Register the Swagger UI as the index page
        $this->kernel->get('/', function (Request $request, Response $response): void {
            $response->setHeader('Content-Type', 'text/html; charset=utf-8');
            $response->setContent($this->renderSwaggerUi());
        });
    }

    /**
     * Register only the OpenAPI spec endpoint (not the UI).
     */
    public function registerSpecEndpoint(): void
    {
        $this->kernel->get($this->specPath, function (Request $request, Response $response): void {
            $response->setHeader('Content-Type', 'application/json');
            $response->setHeader('Access-Control-Allow-Origin', '*');
            $response->setContent($this->generator->toJson());
        });
    }

    /**
     * Render the Swagger UI HTML page.
     */
    private function renderSwaggerUi(): string
    {
        $version = self::SWAGGER_UI_VERSION;
        $title = htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8');
        $specPath = htmlspecialchars($this->specPath, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@{$version}/swagger-ui.css">
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
        .topbar { display: none; }
        .swagger-ui .info { margin: 20px 0; }
        .swagger-ui .info .title { font-size: 2.5em; }
        .dev-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <div class="dev-badge">🚀 Development Mode</div>
    
    <script src="https://unpkg.com/swagger-ui-dist@{$version}/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@{$version}/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "{$specPath}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                persistAuthorization: true,
                tryItOutEnabled: true,
                filter: true,
                validatorUrl: null,
                defaultModelsExpandDepth: 1,
                defaultModelExpandDepth: 3
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
HTML;
    }

    /**
     * Create development routes from configuration.
     */
    public static function createFromConfig(
        HttpKernel $kernel,
        array $config = []
    ): self {
        $title = $config['openapi']['info']['title'] ?? $config['name'] ?? 'API Documentation';
        $version = $config['openapi']['info']['version'] ?? $config['version'] ?? '1.0.0';
        $description = $config['openapi']['info']['description'] ?? '';
        $specPath = $config['openapi']['spec_path'] ?? '/api/openapi.json';

        $generator = new OpenApiGenerator($title, $version, $description);

        // Add servers if configured
        if (isset($config['openapi']['servers'])) {
            foreach ($config['openapi']['servers'] as $server) {
                $generator->addServer(
                    $server['url'] ?? 'http://localhost',
                    $server['description'] ?? ''
                );
            }
        }

        return new self($kernel, $generator, $title, $specPath);
    }
}
