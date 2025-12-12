<?php

declare(strict_types=1);

namespace Lumina\DDD\Infrastructure\Http\Middleware;

use Utopia\Http\Request;
use Utopia\Http\Response;

/**
 * Abstract base class for authentication middleware.
 *
 * Provides common functionality for validating authentication
 * and responding with appropriate errors.
 */
abstract class AbstractAuthMiddleware implements AuthMiddlewareInterface
{
    protected bool $isAuthenticated = false;
    protected mixed $authenticatedUserId = null;
    protected mixed $authenticatedUser = null;

    /**
     * Routes that should skip authentication.
     *
     * @var array<int, string>
     */
    protected array $excludedRoutes = [];

    /**
     * Set routes that should skip authentication.
     *
     * @param array<int, string> $routes
     */
    public function excludeRoutes(array $routes): self
    {
        $this->excludedRoutes = $routes;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        $currentRoute = $request->getURI();

        // Skip authentication for excluded routes
        if ($this->isExcludedRoute($currentRoute)) {
            $next($request, $response);
            return;
        }

        // Attempt authentication
        if (!$this->authenticate($request)) {
            $this->respondUnauthorized($response);
            return;
        }

        $this->isAuthenticated = true;
        $next($request, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthenticated(): bool
    {
        return $this->isAuthenticated;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticatedUserId(): mixed
    {
        return $this->authenticatedUserId;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticatedUser(): mixed
    {
        return $this->authenticatedUser;
    }

    /**
     * Perform authentication logic.
     *
     * Implementations should extract credentials from the request,
     * validate them, and set $authenticatedUserId and $authenticatedUser
     * on success.
     *
     * @return bool True if authentication succeeded
     */
    abstract protected function authenticate(Request $request): bool;

    /**
     * Check if a route should skip authentication.
     */
    protected function isExcludedRoute(string $route): bool
    {
        foreach ($this->excludedRoutes as $excluded) {
            // Support wildcard patterns
            if (str_contains($excluded, '*')) {
                $pattern = '/^' . str_replace(['/', '*'], ['\\/', '.*'], $excluded) . '$/';
                if (preg_match($pattern, $route)) {
                    return true;
                }
            } elseif ($excluded === $route) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send an unauthorized response.
     */
    protected function respondUnauthorized(Response $response): void
    {
        $response->setStatusCode(401);
        $response->json([
            'success' => false,
            'statusCode' => 401,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Authentication required',
            ],
        ]);
    }
}
