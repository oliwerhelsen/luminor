<?php

declare(strict_types=1);

namespace Luminor\DDD\Security\Csrf;

/**
 * CSRF Token Generator
 *
 * Generates and validates CSRF tokens for request protection.
 */
final class CsrfToken
{
    private const TOKEN_LENGTH = 32;

    /**
     * Generate a new CSRF token.
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Verify a CSRF token.
     */
    public static function verify(string $token, string $sessionToken): bool
    {
        if (strlen($token) === 0 || strlen($sessionToken) === 0) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Get the token from the session.
     */
    public static function getFromSession(\Luminor\DDD\Session\SessionInterface $session): ?string
    {
        return $session->get('_csrf_token');
    }

    /**
     * Store the token in the session.
     */
    public static function storeInSession(\Luminor\DDD\Session\SessionInterface $session, string $token): void
    {
        $session->put('_csrf_token', $token);
    }

    /**
     * Regenerate the CSRF token in session.
     */
    public static function regenerate(\Luminor\DDD\Session\SessionInterface $session): string
    {
        $token = self::generate();
        self::storeInSession($session, $token);
        return $token;
    }
}
