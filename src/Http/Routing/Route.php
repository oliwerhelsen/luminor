<?php

declare(strict_types=1);

namespace Luminor\DDD\Http\Routing;

/**
 * Represents a single HTTP route.
 */
class Route
{
    /** @var array<string, string> Extracted parameter names and their regex patterns */
    private array $parameterPatterns = [];

    /** @var array<class-string> Middleware classes for this route */
    private array $middleware = [];

    private ?string $name = null;
    private string $regex;

    /**
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE, etc.)
     * @param string $path URL path pattern with optional parameters (e.g., /users/:id)
     * @param callable|array{0: class-string, 1: string} $handler Route handler
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private mixed $handler
    ) {
        $this->regex = $this->compilePattern($path);
    }

    /**
     * Get the HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the path pattern.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the route handler.
     *
     * @return callable|array{0: class-string, 1: string}
     */
    public function getHandler(): callable|array
    {
        return $this->handler;
    }

    /**
     * Set the route handler.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function setHandler(callable|array $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * Get the compiled regex pattern.
     */
    public function getRegex(): string
    {
        return $this->regex;
    }

    /**
     * Get the route name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the route name.
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the middleware for this route.
     *
     * @return array<class-string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Add middleware to this route.
     *
     * @param class-string|array<class-string> $middleware
     */
    public function middleware(string|array $middleware): self
    {
        $middlewareList = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $middlewareList);
        return $this;
    }

    /**
     * Check if this route matches the given method and path.
     *
     * @param array<string, string> &$matches Extracted route parameters
     */
    public function matches(string $method, string $path, array &$matches = []): bool
    {
        // Check method
        if ($this->method !== $method && $this->method !== 'ANY') {
            return false;
        }

        // Check path pattern
        if (preg_match($this->regex, $path, $pathMatches)) {
            // Extract named parameters
            foreach ($this->parameterPatterns as $name => $pattern) {
                if (isset($pathMatches[$name])) {
                    $matches[$name] = $pathMatches[$name];
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Generate a URL for this route with the given parameters.
     *
     * @param array<string, string|int> $parameters
     */
    public function url(array $parameters = []): string
    {
        $path = $this->path;

        foreach ($parameters as $name => $value) {
            $path = str_replace(':' . $name, (string) $value, $path);
        }

        return $path;
    }

    /**
     * Compile the path pattern into a regex.
     */
    private function compilePattern(string $path): string
    {
        // Escape regex special characters except for our parameter syntax
        $regex = preg_quote($path, '#');

        // Replace :param patterns with named capture groups
        $regex = preg_replace_callback(
            '/\\\\:([a-zA-Z_][a-zA-Z0-9_]*)/',
            function (array $match): string {
                $name = $match[1];
                $this->parameterPatterns[$name] = '[^/]+';
                return '(?P<' . $name . '>[^/]+)';
            },
            $regex
        );

        return '#^' . $regex . '$#';
    }
}
