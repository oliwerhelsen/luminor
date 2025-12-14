<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Infrastructure\Http;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Infrastructure\Http\Middleware\CorsMiddleware;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

final class CorsMiddlewareTest extends TestCase
{
    public function testAllowAllOriginsCreatesMiddleware(): void
    {
        $middleware = CorsMiddleware::allowAll();

        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function testWithOriginsCreatesMiddleware(): void
    {
        $middleware = CorsMiddleware::withOrigins(['https://example.com']);

        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function testSetAllowedOriginsReturnsSelf(): void
    {
        $middleware = new CorsMiddleware();
        $result = $middleware->setAllowedOrigins(['https://example.com']);

        $this->assertSame($middleware, $result);
    }

    public function testSetAllowedMethodsReturnsSelf(): void
    {
        $middleware = new CorsMiddleware();
        $result = $middleware->setAllowedMethods(['GET', 'POST']);

        $this->assertSame($middleware, $result);
    }

    public function testSetAllowedHeadersReturnsSelf(): void
    {
        $middleware = new CorsMiddleware();
        $result = $middleware->setAllowedHeaders(['Content-Type', 'Authorization']);

        $this->assertSame($middleware, $result);
    }

    public function testSetExposedHeadersReturnsSelf(): void
    {
        $middleware = new CorsMiddleware();
        $result = $middleware->setExposedHeaders(['X-Custom-Header']);

        $this->assertSame($middleware, $result);
    }

    public function testSetAllowCredentialsReturnsSelf(): void
    {
        $middleware = new CorsMiddleware();
        $result = $middleware->setAllowCredentials(true);

        $this->assertSame($middleware, $result);
    }

    public function testSetMaxAgeReturnsSelf(): void
    {
        $middleware = new CorsMiddleware();
        $result = $middleware->setMaxAge(3600);

        $this->assertSame($middleware, $result);
    }

    public function testDefaultConfigurationValues(): void
    {
        $middleware = new CorsMiddleware();

        // Test that default values can be set via fluent interface
        $middleware
            ->setAllowedOrigins(['*'])
            ->setAllowedMethods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])
            ->setAllowedHeaders(['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'])
            ->setExposedHeaders([])
            ->setAllowCredentials(false)
            ->setMaxAge(86400);

        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function testMiddlewareCanBeChainedConfigured(): void
    {
        $middleware = CorsMiddleware::withOrigins(['https://example.com', 'https://api.example.com'])
            ->setAllowedMethods(['GET', 'POST'])
            ->setAllowedHeaders(['Content-Type', 'Authorization'])
            ->setExposedHeaders(['X-Rate-Limit'])
            ->setAllowCredentials(true)
            ->setMaxAge(3600);

        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }
}
