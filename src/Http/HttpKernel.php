<?php

declare(strict_types=1);

namespace Luminor\Http;

use Luminor\Http\OpenApi\OpenApiDevelopmentRoutes;
use Luminor\Http\OpenApi\OpenApiGenerator;
use Luminor\Http\Routing\Router;

/**
 * HTTP Kernel for handling HTTP requests.
 *
 * This kernel provides the entry point for HTTP request handling,
 * coordinating routing, middleware, and response generation.
 */
class HttpKernel
{
    private static ?HttpKernel $instance = null;

    private Router $router;

    private ?OpenApiGenerator $openApiGenerator = null;

    /** @var callable|null Error handler */
    private $errorHandler = null;

    public function __construct(?Router $router = null)
    {
        $this->router = $router ?? Router::getInstance();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Reset the singleton (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
        Router::resetInstance();
    }

    /**
     * Get the router.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Register an error handler.
     *
     * @param callable(\Throwable, Request, Response): void $handler
     */
    public function onError(callable $handler): self
    {
        $this->errorHandler = $handler;
        return $this;
    }

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(?Request $request = null): Response
    {
        $request = $request ?? Request::createFromGlobals();
        $response = new Response();

        try {
            $this->router->dispatch($request, $response);
        } catch (\Throwable $e) {
            $this->handleException($e, $request, $response);
        }

        return $response;
    }

    /**
     * Handle the request and send the response.
     */
    public function run(?Request $request = null): void
    {
        $response = $this->handle($request);
        $response->send();
    }

    /**
     * Handle an exception.
     */
    private function handleException(\Throwable $e, Request $request, Response $response): void
    {
        if ($this->errorHandler !== null) {
            ($this->errorHandler)($e, $request, $response);
            return;
        }

        // Default error handling
        $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->json([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
        ]);
    }

    // ========================================
    // Convenience methods for route registration
    // ========================================

    /**
     * Register a GET route.
     */
    public function get(string $path, callable|array $handler): Routing\Route
    {
        return $this->router->get($path, $handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, callable|array $handler): Routing\Route
    {
        return $this->router->post($path, $handler);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, callable|array $handler): Routing\Route
    {
        return $this->router->put($path, $handler);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, callable|array $handler): Routing\Route
    {
        return $this->router->patch($path, $handler);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, callable|array $handler): Routing\Route
    {
        return $this->router->delete($path, $handler);
    }

    /**
     * Create a route group.
     *
     * @param array{prefix?: string, middleware?: array<class-string>} $attributes
     * @param callable(Router): void $callback
     */
    public function group(array $attributes, callable $callback): self
    {
        $this->router->group($attributes, $callback);
        return $this;
    }

    /**
     * Register resource routes.
     *
     * @param class-string $controller
     * @param array{only?: array<string>, except?: array<string>} $options
     */
    public function resource(string $path, string $controller, array $options = []): self
    {
        $this->router->resource($path, $controller, $options);
        return $this;
    }

    /**
     * Add global middleware.
     *
     * @param class-string|array<class-string> $middleware
     */
    public function pushMiddleware(string|array $middleware): self
    {
        $this->router->pushMiddleware($middleware);
        return $this;
    }

    // ========================================
    // Development Mode Features
    // ========================================

    /**
     * Enable Swagger UI as the index page in development mode.
     *
     * This registers:
     * - "/" - Swagger UI for interactive API documentation
     * - "/api/openapi.json" - OpenAPI specification endpoint
     *
     * @param array{
     *     name?: string,
     *     version?: string,
     *     openapi?: array{
     *         info?: array{title?: string, version?: string, description?: string},
     *         spec_path?: string,
     *         servers?: array<array{url: string, description?: string}>
     *     }
     * } $config Application configuration
     */
    public function enableDevelopmentDocs(array $config = []): self
    {
        $devRoutes = OpenApiDevelopmentRoutes::createFromConfig($this, $config);
        $devRoutes->register();

        return $this;
    }

    /**
     * Get or create the OpenAPI generator for this kernel.
     */
    public function getOpenApiGenerator(): OpenApiGenerator
    {
        if ($this->openApiGenerator === null) {
            $this->openApiGenerator = new OpenApiGenerator('API', '1.0.0');
        }

        return $this->openApiGenerator;
    }

    /**
     * Set the OpenAPI generator for this kernel.
     */
    public function setOpenApiGenerator(OpenApiGenerator $generator): self
    {
        $this->openApiGenerator = $generator;
        return $this;
    }
}
