<?php

declare(strict_types=1);

namespace Luminor\Auth;

use RuntimeException;

/**
 * Exception thrown when authentication fails or is required but missing.
 */
final class AuthenticationException extends RuntimeException
{
    public function __construct(
        string $message = 'Unauthenticated.',
        int $code = 401,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for invalid credentials.
     */
    public static function invalidCredentials(): self
    {
        return new self('The provided credentials are incorrect.');
    }

    /**
     * Create exception for expired token.
     */
    public static function tokenExpired(): self
    {
        return new self('The authentication token has expired.');
    }

    /**
     * Create exception for invalid token.
     */
    public static function invalidToken(): self
    {
        return new self('The authentication token is invalid.');
    }

    /**
     * Create exception when user is not found.
     */
    public static function userNotFound(): self
    {
        return new self('User not found.');
    }

    /**
     * Create exception when user account is disabled.
     */
    public static function accountDisabled(): self
    {
        return new self('Your account has been disabled.');
    }
}
