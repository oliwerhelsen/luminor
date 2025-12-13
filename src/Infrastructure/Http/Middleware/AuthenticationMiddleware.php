<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Http\Middleware;

use Luminor\Auth\AuthenticationException;
use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\CurrentUser;
use Luminor\Http\Middleware;
use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Authentication Middleware using AuthenticationManager
 * Supports multiple authentication methods (JWT, Session, OIDC, API Tokens)
 */
class AuthenticationMiddleware implements Middleware
{
    private AuthenticationManager $authManager;
    private bool $required;
    private array $excludedRoutes;

    public function __construct(
        AuthenticationManager $authManager,
        bool $required = true,
        array $excludedRoutes = []
    ) {
        $this->authManager = $authManager;
        $this->required = $required;
        $this->excludedRoutes = $excludedRoutes;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Check if route is excluded
        if ($this->isExcluded($request->getPath())) {
            return $next($request);
        }

        try {
            $user = $this->authManager->authenticate($request);

            if ($user) {
                CurrentUser::set($user);
            } elseif ($this->required) {
                return $this->respondUnauthorized('Authentication required');
            }
        } catch (AuthenticationException $e) {
            if ($this->required) {
                return $this->respondUnauthorized($e->getMessage());
            }
        }

        $response = $next($request);

        // Clear current user after request
        CurrentUser::clear();

        return $response;
    }

    /**
     * Check if route is excluded from authentication
     *
     * @param string $path
     * @return bool
     */
    private function isExcluded(string $path): bool
    {
        foreach ($this->excludedRoutes as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path matches pattern (supports wildcards)
     *
     * @param string $path
     * @param string $pattern
     * @return bool
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Wildcard match
        if (str_contains($pattern, '*')) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            return preg_match($regex, $path) === 1;
        }

        return false;
    }

    /**
     * Return unauthorized response
     *
     * @param string $message
     * @return Response
     */
    private function respondUnauthorized(string $message): Response
    {
        return Response::json([
            'error' => 'Unauthorized',
            'message' => $message,
        ], 401);
    }

    /**
     * Exclude specific routes from authentication
     *
     * @param array $routes
     * @return self
     */
    public function except(array $routes): self
    {
        $this->excludedRoutes = array_merge($this->excludedRoutes, $routes);
        return $this;
    }
}
