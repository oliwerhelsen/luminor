<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http;

use Luminor\DDD\Http\HttpKernel;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;
use Luminor\DDD\Http\Routing\Route;
use Luminor\DDD\Http\Routing\Router;
use Luminor\DDD\Infrastructure\Http\Middleware\MiddlewareInterface;

/**
 * Route registration helper for DDD controllers.
 *
 * Provides a fluent interface for registering routes and
 * attaching middleware and controllers.
 */
final class RouteRegistrar
{
    /**
     * Registered middleware.
     *
     * @var array<int, class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    /**
     * Route prefix.
     */
    private string $prefix = '';

    /**
     * Create a new registrar instance.
     */
    public function __construct(
        private readonly Router $router
    ) {
    }

    /**
     * Create a new registrar for the default router.
     */
    public static function create(): self
    {
        return new self(Router::getInstance());
    }

    /**
     * Create a new registrar for a specific router.
     */
    public static function for(Router $router): self
    {
        return new self($router);
    }

    /**
     * Create a registrar from the HTTP kernel.
     */
    public static function fromKernel(HttpKernel $kernel): self
    {
        return new self($kernel->getRouter());
    }

    /**
     * Set a route prefix.
     */
    public function prefix(string $prefix): self
    {
        $instance = clone $this;
        $instance->prefix = rtrim($prefix, '/');
        return $instance;
    }

    /**
     * Add middleware to apply to routes.
     *
     * @param class-string<MiddlewareInterface>|array<int, class-string<MiddlewareInterface>> $middleware
     */
    public function middleware(string|array $middleware): self
    {
        $instance = clone $this;
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        $instance->middleware = array_merge($this->middleware, $middlewares);
        return $instance;
    }

    /**
     * Register a GET route.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function get(string $path, callable|array $handler): Route
    {
        return $this->register('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function post(string $path, callable|array $handler): Route
    {
        return $this->register('POST', $path, $handler);
    }

    /**
     * Register a PUT route.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function put(string $path, callable|array $handler): Route
    {
        return $this->register('PUT', $path, $handler);
    }

    /**
     * Register a PATCH route.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function patch(string $path, callable|array $handler): Route
    {
        return $this->register('PATCH', $path, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function delete(string $path, callable|array $handler): Route
    {
        return $this->register('DELETE', $path, $handler);
    }

    /**
     * Register resource routes for a CRUD controller.
     *
     * Creates standard REST routes:
     * - GET    /resources       -> index
     * - GET    /resources/:id   -> show
     * - POST   /resources       -> store
     * - PUT    /resources/:id   -> update
     * - DELETE /resources/:id   -> destroy
     *
     * @param class-string<CrudController> $controllerClass
     * @param array<int, string> $only Only register these methods
     * @param array<int, string> $except Exclude these methods
     * @return array<string, Route>
     */
    public function resource(
        string $path,
        string $controllerClass,
        array $only = [],
        array $except = [],
        string $idParam = 'id'
    ): array {
        $path = rtrim($path, '/');
        $routes = [];
        $defaultMethods = ['index', 'show', 'store', 'update', 'destroy'];

        $methods = count($only) > 0
            ? array_intersect($defaultMethods, $only)
            : array_diff($defaultMethods, $except);

        if (in_array('index', $methods, true)) {
            $routes['index'] = $this->get($path, [$controllerClass, 'index']);
        }

        if (in_array('show', $methods, true)) {
            $routes['show'] = $this->get("{$path}/:{$idParam}", [$controllerClass, 'show']);
        }

        if (in_array('store', $methods, true)) {
            $routes['store'] = $this->post($path, [$controllerClass, 'store']);
        }

        if (in_array('update', $methods, true)) {
            $routes['update'] = $this->put("{$path}/:{$idParam}", [$controllerClass, 'update']);
        }

        if (in_array('destroy', $methods, true)) {
            $routes['destroy'] = $this->delete("{$path}/:{$idParam}", [$controllerClass, 'destroy']);
        }

        return $routes;
    }

    /**
     * Group routes with shared configuration.
     *
     * @param callable(RouteRegistrar): void $callback
     */
    public function group(callable $callback): void
    {
        $callback($this);
    }

    /**
     * Register a route with the router.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function register(string $method, string $path, callable|array $handler): Route
    {
        $fullPath = $this->prefix . '/' . ltrim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');

        $route = $this->router->addRoute($method, $fullPath, $handler);

        // Apply middleware to the route
        if (!empty($this->middleware)) {
            $route->middleware($this->middleware);
        }

        return $route;
    }

    /**
     * Create an API route group with JSON responses.
     *
     * @param callable(RouteRegistrar): void $callback
     */
    public function api(string $prefix, callable $callback): void
    {
        $registrar = $this->prefix("/api{$prefix}");
        $callback($registrar);
    }

    /**
     * Get the underlying router.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
}
