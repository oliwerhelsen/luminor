<?php

declare(strict_types=1);

namespace Luminor\Tests\Unit\Http\OpenApi;

use Luminor\Http\OpenApi\OpenApiGenerator;
use PHPUnit\Framework\TestCase;

final class OpenApiGeneratorTest extends TestCase
{
    private OpenApiGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OpenApiGenerator(
            title: 'Test API',
            version: '1.0.0',
            description: 'API for testing'
        );
    }

    public function testInitialization(): void
    {
        $spec = $this->generator->generate();

        $this->assertEquals('3.0.0', $spec['openapi']);
        $this->assertEquals('Test API', $spec['info']['title']);
        $this->assertEquals('1.0.0', $spec['info']['version']);
        $this->assertEquals('API for testing', $spec['info']['description']);
    }

    public function testAddServer(): void
    {
        $this->generator->addServer('https://api.example.com', 'Production');

        $spec = $this->generator->generate();

        $this->assertCount(1, $spec['servers']);
        $this->assertEquals('https://api.example.com', $spec['servers'][0]['url']);
        $this->assertEquals('Production', $spec['servers'][0]['description']);
    }

    public function testAddSchema(): void
    {
        $this->generator->addSchema('Product', [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
        ]);

        $spec = $this->generator->generate();

        $this->assertArrayHasKey('Product', $spec['components']['schemas']);
        $this->assertEquals('object', $spec['components']['schemas']['Product']['type']);
    }

    public function testAddSecurityScheme(): void
    {
        $this->generator->addSecurityScheme('bearerAuth', [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        $spec = $this->generator->generate();

        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
        $this->assertEquals('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
    }

    public function testAddRoute(): void
    {
        $this->generator->addRoute(
            method: 'GET',
            path: '/products',
            summary: 'List products',
            description: 'Get all products',
            parameters: [],
            requestBody: [],
            responses: [
                200 => [
                    'description' => 'Success',
                ],
            ]
        );

        $spec = $this->generator->generate();

        $this->assertArrayHasKey('/products', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/products']);
        $this->assertEquals('List products', $spec['paths']['/products']['get']['summary']);
    }

    public function testToJson(): void
    {
        $json = $this->generator->toJson();

        $this->assertIsString($json);
        $this->assertNotEmpty($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('Test API', $decoded['info']['title']);
    }

    public function testToYaml(): void
    {
        $yaml = $this->generator->toYaml();

        $this->assertIsString($yaml);
        $this->assertStringContainsString('openapi:', $yaml);
        $this->assertStringContainsString('title:', $yaml);
    }
}
