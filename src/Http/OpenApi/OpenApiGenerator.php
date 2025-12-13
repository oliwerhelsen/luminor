<?php

declare(strict_types=1);

namespace Luminor\DDD\Http\OpenApi;

/**
 * OpenAPI specification generator.
 *
 * Generates OpenAPI 3.0 documentation from controller annotations and routes.
 */
final class OpenApiGenerator
{
    /** @var array<string, mixed> */
    private array $spec;

    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    public function __construct(
        private readonly string $title,
        private readonly string $version,
        private readonly string $description = ''
    ) {
        $this->spec = $this->initializeSpec();
    }

    /**
     * Register a route for documentation.
     */
    public function addRoute(
        string $method,
        string $path,
        string $summary,
        string $description = '',
        array $parameters = [],
        array $requestBody = [],
        array $responses = []
    ): void {
        $this->routes[] = [
            'method' => strtolower($method),
            'path' => $path,
            'summary' => $summary,
            'description' => $description,
            'parameters' => $parameters,
            'requestBody' => $requestBody,
            'responses' => $responses,
        ];
    }

    /**
     * Generate the OpenAPI specification.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $paths = [];

        foreach ($this->routes as $route) {
            $path = $route['path'];
            $method = $route['method'];

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $operation = [
                'summary' => $route['summary'],
                'description' => $route['description'],
                'parameters' => $route['parameters'],
                'responses' => $this->formatResponses($route['responses']),
            ];

            if (!empty($route['requestBody'])) {
                $operation['requestBody'] = $this->formatRequestBody($route['requestBody']);
            }

            $paths[$path][$method] = $operation;
        }

        $this->spec['paths'] = $paths;

        return $this->spec;
    }

    /**
     * Generate the specification as JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate the specification as YAML.
     */
    public function toYaml(): string
    {
        return $this->arrayToYaml($this->generate());
    }

    /**
     * Add a schema definition.
     */
    public function addSchema(string $name, array $schema): void
    {
        if (!isset($this->spec['components']['schemas'])) {
            $this->spec['components']['schemas'] = [];
        }

        $this->spec['components']['schemas'][$name] = $schema;
    }

    /**
     * Add a security scheme.
     */
    public function addSecurityScheme(string $name, array $scheme): void
    {
        if (!isset($this->spec['components']['securitySchemes'])) {
            $this->spec['components']['securitySchemes'] = [];
        }

        $this->spec['components']['securitySchemes'][$name] = $scheme;
    }

    /**
     * Add a server.
     */
    public function addServer(string $url, string $description = ''): void
    {
        $this->spec['servers'][] = [
            'url' => $url,
            'description' => $description,
        ];
    }

    /**
     * Initialize the base OpenAPI specification.
     *
     * @return array<string, mixed>
     */
    private function initializeSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
                'description' => $this->description,
            ],
            'servers' => [],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [],
            ],
        ];
    }

    /**
     * Format responses for OpenAPI.
     *
     * @param array<string, mixed> $responses
     * @return array<string, mixed>
     */
    private function formatResponses(array $responses): array
    {
        if (empty($responses)) {
            return [
                '200' => [
                    'description' => 'Successful response',
                ],
            ];
        }

        $formatted = [];

        foreach ($responses as $code => $response) {
            $formatted[(string) $code] = $response;
        }

        return $formatted;
    }

    /**
     * Format request body for OpenAPI.
     *
     * @param array<string, mixed> $requestBody
     * @return array<string, mixed>
     */
    private function formatRequestBody(array $requestBody): array
    {
        return [
            'required' => $requestBody['required'] ?? true,
            'content' => [
                'application/json' => [
                    'schema' => $requestBody['schema'] ?? [],
                ],
            ],
        ];
    }

    /**
     * Convert array to YAML format.
     *
     * Simple YAML converter for basic structures.
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $spaces . $key . ":\n";
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $spaces . $key . ': ' . (is_string($value) ? "'{$value}'" : $value) . "\n";
            }
        }

        return $yaml;
    }
}
