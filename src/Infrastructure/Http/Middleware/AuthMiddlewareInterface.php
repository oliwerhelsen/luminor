<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http\Middleware;

use Utopia\Http\Request;
use Utopia\Http\Response;

/**
 * Interface for authentication middleware.
 *
 * Authentication middleware validates user credentials and
 * populates the request context with authenticated user information.
 */
interface AuthMiddlewareInterface extends MiddlewareInterface
{
    /**
     * Check if the current request is authenticated.
     */
    public function isAuthenticated(): bool;

    /**
     * Get the authenticated user identifier.
     *
     * @return mixed The user identifier or null if not authenticated
     */
    public function getAuthenticatedUserId(): mixed;

    /**
     * Get the authenticated user.
     *
     * @return mixed The user object or null if not authenticated
     */
    public function getAuthenticatedUser(): mixed;
}
