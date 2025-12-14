<?php

declare(strict_types=1);

namespace Luminor\Http\Routing;

use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Simple HTTP router for matching requests to handlers.
 */
class Router
{
    /** @var array<Route> Registered routes */
    private array $routes = [];

    /** @var array<string, Route> Named routes for URL generation */
    private array $namedRoutes = [];

    /** @var array<class-string> Global middleware applied to all routes */
    private array $globalMiddleware = [];

    /** @var string Current route group prefix */
    private string $groupPrefix = '';

    /** @var array<class-string> Current group middleware */
    private array $groupMiddleware = [];

    private static ?Router $instance = null;

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
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, callable|array $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, callable|array $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, callable|array $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register a route for any HTTP method.
     */
    public function any(string $path, callable|array $handler): Route
    {
        return $this->addRoute('ANY', $path, $handler);
    }

    /**
     * Register a route for multiple HTTP methods.
     *
     * @param array<string> $methods
     */
    public function methods(array $methods, string $path, callable|array $handler): Route
    {
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $path, $handler);
        }
        return $route;
    }

    /**
     * Add a route.
     */
    public function addRoute(string $method, string $path, callable|array $handler): Route
    {
        $fullPath = $this->groupPrefix . $path;
        $route = new Route($method, $fullPath, $handler);

        // Apply group middleware
        if (!empty($this->groupMiddleware)) {
            $route->middleware($this->groupMiddleware);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param array{prefix?: string, middleware?: array<class-string>} $attributes
     * @param callable(Router): void $callback
     */
    public function group(array $attributes, callable $callback): self
    {
        // Save current state
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        // Apply group attributes
        if (isset($attributes['prefix'])) {
            $this->groupPrefix .= $attributes['prefix'];
        }

        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge($this->groupMiddleware, $attributes['middleware']);
        }

        // Execute the group callback
        $callback($this);

        // Restore previous state
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    /**
     * Add global middleware.
     *
     * @param class-string|array<class-string> $middleware
     */
    public function pushMiddleware(string|array $middleware): self
    {
        $middlewareList = is_array($middleware) ? $middleware : [$middleware];
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middlewareList);
        return $this;
    }

    /**
     * Get global middleware.
     *
     * @return array<class-string>
     */
    public function getGlobalMiddleware(): array
    {
        return $this->globalMiddleware;
    }

    /**
     * Register resource routes (RESTful CRUD).
     *
     * @param class-string $controller
     * @param array{only?: array<string>, except?: array<string>} $options
     */
    public function resource(string $path, string $controller, array $options = []): self
    {
        $resourceRoutes = [
            'index' => ['GET', ''],
            'store' => ['POST', ''],
            'show' => ['GET', '/:id'],
            'update' => ['PUT', '/:id'],
            'destroy' => ['DELETE', '/:id'],
        ];

        // Filter routes based on options
        if (isset($options['only'])) {
            $resourceRoutes = array_intersect_key($resourceRoutes, array_flip($options['only']));
        }

        if (isset($options['except'])) {
            $resourceRoutes = array_diff_key($resourceRoutes, array_flip($options['except']));
        }

        foreach ($resourceRoutes as $action => [$method, $suffix]) {
            $this->addRoute($method, $path . $suffix, [$controller, $action]);
        }

        return $this;
    }

    /**
     * Find a route matching the request.
     *
     * @param array<string, string> &$parameters Extracted route parameters
     */
    public function match(Request $request, array &$parameters = []): ?Route
    {
        $method = $request->getMethod();
        $path = $request->getUri();

        // Normalize path
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route->matches($method, $path, $parameters)) {
                return $route;
            }
        }

        // Check for OPTIONS preflight
        if ($method === 'OPTIONS') {
            foreach ($this->routes as $route) {
                $tempParams = [];
                if ($route->matches('GET', $path, $tempParams) ||
                    $route->matches('POST', $path, $tempParams) ||
                    $route->matches('PUT', $path, $tempParams) ||
                    $route->matches('PATCH', $path, $tempParams) ||
                    $route->matches('DELETE', $path, $tempParams)) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Dispatch the request to the appropriate handler.
     */
    public function dispatch(Request $request, Response $response): Response
    {
        $parameters = [];
        $route = $this->match($request, $parameters);

        if ($route === null) {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
            $response->json([
                'error' => 'Not Found',
                'message' => sprintf('Route not found for %s %s', $request->getMethod(), $request->getUri()),
            ]);
            return $response;
        }

        // Set route parameters as request attributes
        foreach ($parameters as $name => $value) {
            $request->setAttribute($name, $value);
        }

        // Build middleware pipeline
        $middleware = array_merge($this->globalMiddleware, $route->getMiddleware());
        $handler = $route->getHandler();

        // Execute the pipeline
        $pipeline = $this->buildPipeline($middleware, $handler);
        $pipeline($request, $response);

        return $response;
    }

    /**
     * Build the middleware pipeline.
     *
     * @param array<class-string> $middleware
     * @param callable|array{0: class-string, 1: string} $handler
     * @return callable(Request, Response): void
     */
    private function buildPipeline(array $middleware, callable|array $handler): callable
    {
        // Create the final handler
        $finalHandler = function (Request $request, Response $response) use ($handler): void {
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $controller = new $class();
                $controller->$method($request, $response);
            } else {
                $handler($request, $response);
            }
        };

        // Wrap with middleware (in reverse order)
        $pipeline = $finalHandler;

        foreach (array_reverse($middleware) as $middlewareClass) {
            $next = $pipeline;
            $pipeline = function (Request $request, Response $response) use ($middlewareClass, $next): void {
                $middlewareInstance = new $middlewareClass();
                $middlewareInstance->handle($request, $response, $next);
            };
        }

        return $pipeline;
    }

    /**
     * Get all registered routes.
     *
     * @return array<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get a named route.
     */
    public function getRoute(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Generate a URL for a named route.
     *
     * @param array<string, string|int> $parameters
     */
    public function url(string $name, array $parameters = []): ?string
    {
        $route = $this->getRoute($name);
        return $route?->url($parameters);
    }

    /**
     * Clear all routes (useful for testing).
     */
    public function clear(): self
    {
        $this->routes = [];
        $this->namedRoutes = [];
        $this->globalMiddleware = [];
        $this->groupPrefix = '';
        $this->groupMiddleware = [];
        return $this;
    }
}
