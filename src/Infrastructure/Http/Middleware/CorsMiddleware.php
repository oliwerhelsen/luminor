<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Http\Middleware;

use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Middleware for handling Cross-Origin Resource Sharing (CORS).
 *
 * Enables cross-origin requests by setting appropriate headers
 * and handling preflight OPTIONS requests.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    private const DEFAULT_MAX_AGE = 86400; // 24 hours

    /**
     * @param array<int, string> $allowedOrigins Allowed origins (use ['*'] for all)
     * @param array<int, string> $allowedMethods Allowed HTTP methods
     * @param array<int, string> $allowedHeaders Allowed request headers
     * @param array<int, string> $exposedHeaders Headers exposed to the client
     * @param bool $allowCredentials Whether to allow credentials
     * @param int $maxAge Preflight cache duration in seconds
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
        private array $exposedHeaders = [],
        private bool $allowCredentials = false,
        private int $maxAge = self::DEFAULT_MAX_AGE
    ) {
    }

    /**
     * Create with specific allowed origins.
     *
     * @param array<int, string> $origins
     */
    public static function withOrigins(array $origins): self
    {
        return new self(allowedOrigins: $origins);
    }

    /**
     * Create with all origins allowed.
     */
    public static function allowAll(): self
    {
        return new self(allowedOrigins: ['*']);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        $origin = $request->getHeader('origin') ?? '';

        // Set CORS headers
        $this->setCorsHeaders($response, $origin);

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $this->handlePreflight($response);
            return;
        }

        $next($request, $response);
    }

    /**
     * Set CORS headers on the response.
     */
    private function setCorsHeaders(Response $response, string $requestOrigin): void
    {
        // Determine allowed origin
        $allowedOrigin = $this->determineAllowedOrigin($requestOrigin);
        if ($allowedOrigin !== null) {
            $response->addHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }

        // Set Vary header for proper caching
        if ($this->allowedOrigins !== ['*']) {
            $response->addHeader('Vary', 'Origin');
        }

        // Set credentials header
        if ($this->allowCredentials) {
            $response->addHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Set exposed headers
        if (count($this->exposedHeaders) > 0) {
            $response->addHeader('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        }
    }

    /**
     * Handle preflight OPTIONS request.
     */
    private function handlePreflight(Response $response): void
    {
        $response->addHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response->addHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
        $response->addHeader('Access-Control-Max-Age', (string) $this->maxAge);
        $response->setStatusCode(204);
        $response->noContent();
    }

    /**
     * Determine if the request origin is allowed.
     */
    private function determineAllowedOrigin(string $requestOrigin): ?string
    {
        // Allow all origins
        if ($this->allowedOrigins === ['*']) {
            // When credentials are allowed, we must return the specific origin
            return $this->allowCredentials ? $requestOrigin : '*';
        }

        // Check if origin is in allowed list
        if (in_array($requestOrigin, $this->allowedOrigins, true)) {
            return $requestOrigin;
        }

        // Check for wildcard patterns
        foreach ($this->allowedOrigins as $allowed) {
            if (str_contains($allowed, '*')) {
                $pattern = '/^' . str_replace(['/', '*'], ['\\/', '.*'], $allowed) . '$/';
                if (preg_match($pattern, $requestOrigin)) {
                    return $requestOrigin;
                }
            }
        }

        return null;
    }

    /**
     * Set allowed origins.
     *
     * @param array<int, string> $origins
     */
    public function setAllowedOrigins(array $origins): self
    {
        $this->allowedOrigins = $origins;
        return $this;
    }

    /**
     * Set allowed methods.
     *
     * @param array<int, string> $methods
     */
    public function setAllowedMethods(array $methods): self
    {
        $this->allowedMethods = $methods;
        return $this;
    }

    /**
     * Set allowed headers.
     *
     * @param array<int, string> $headers
     */
    public function setAllowedHeaders(array $headers): self
    {
        $this->allowedHeaders = $headers;
        return $this;
    }

    /**
     * Set exposed headers.
     *
     * @param array<int, string> $headers
     */
    public function setExposedHeaders(array $headers): self
    {
        $this->exposedHeaders = $headers;
        return $this;
    }

    /**
     * Enable or disable credentials.
     */
    public function setAllowCredentials(bool $allow): self
    {
        $this->allowCredentials = $allow;
        return $this;
    }

    /**
     * Set preflight cache max age.
     */
    public function setMaxAge(int $seconds): self
    {
        $this->maxAge = $seconds;
        return $this;
    }
}
