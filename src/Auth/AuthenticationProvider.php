<?php

declare(strict_types=1);

namespace Luminor\Auth;

use Luminor\Http\Request;

/**
 * Interface for authentication providers (OIDC, JWT, Session, etc.)
 */
interface AuthenticationProvider
{
    /**
     * Authenticate a user from the request
     *
     * @param Request $request
     * @return AuthenticatableInterface|null
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): ?AuthenticatableInterface;

    /**
     * Check if this provider can handle the current request
     *
     * @param Request $request
     * @return bool
     */
    public function supports(Request $request): bool;

    /**
     * Get the provider name
     *
     * @return string
     */
    public function getName(): string;
}
