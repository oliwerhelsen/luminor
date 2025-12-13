<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http;

use InvalidArgumentException;
use Luminor\DDD\Infrastructure\Http\Middleware\MiddlewareInterface;
use Utopia\Http\Http;
use Utopia\Http\Request;
use Utopia\Http\Response;
use Utopia\Http\Route;

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
     * @var array<int, MiddlewareInterface>
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
        private readonly Http $http,
    ) {
    }

    /**
     * Create a new registrar for an Http instance.
     */
    public static function for(Http $http): self
    {
        return new self($http);
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
     * @param MiddlewareInterface|array<int, MiddlewareInterface> $middleware
     */
    public function middleware(MiddlewareInterface|array $middleware): self
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
     *
     * @return array<string, Route>
     */
    public function resource(
        string $path,
        string $controllerClass,
        array $only = [],
        array $except = [],
        string $idParam = 'id',
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
     * Register a route with the HTTP instance.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function register(string $method, string $path, callable|array $handler): Route
    {
        $fullPath = $this->prefix . '/' . ltrim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');

        // Create the wrapped handler with middleware
        $wrappedHandler = $this->wrapWithMiddleware($handler);

        // Register with Utopia HTTP
        $route = match ($method) {
            'GET' => $this->http->get($fullPath),
            'POST' => $this->http->post($fullPath),
            'PUT' => $this->http->put($fullPath),
            'PATCH' => $this->http->patch($fullPath),
            'DELETE' => $this->http->delete($fullPath),
            default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        // Set the action
        $route->action($wrappedHandler);

        return $route;
    }

    /**
     * Wrap a handler with middleware.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function wrapWithMiddleware(callable|array $handler): callable
    {
        $middleware = $this->middleware;

        return function (Request $request, Response $response) use ($handler, $middleware): void {
            // Build middleware pipeline
            $pipeline = function (Request $request, Response $response) use ($handler): void {
                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $instance = new $class();
                    $instance->$method($request, $response);
                } else {
                    $handler($request, $response);
                }
            };

            // Apply middleware in reverse order
            foreach (array_reverse($middleware) as $m) {
                $pipeline = fn (Request $req, Response $res) => $m->handle($req, $res, $pipeline);
            }

            $pipeline($request, $response);
        };
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
}
