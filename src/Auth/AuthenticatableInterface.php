<?php

declare(strict_types=1);

namespace Luminor\Auth;

/**
 * Interface for entities that can be authenticated.
 *
 * Implement this interface on your User entity or any other
 * entity that represents an authenticated actor in the system.
 */
interface AuthenticatableInterface
{
    /**
     * Get the unique identifier for the authenticatable entity.
     *
     * @return string|int The unique identifier
     */
    public function getAuthIdentifier(): string|int;

    /**
     * Get the name of the unique identifier field.
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password hash for authentication.
     *
     * @return string|null The hashed password or null if not applicable
     */
    public function getAuthPassword(): ?string;

    /**
     * Get the token value for "remember me" functionality.
     *
     * @return string|null The remember token or null if not set
     */
    public function getRememberToken(): ?string;

    /**
     * Set the token value for "remember me" functionality.
     */
    public function setRememberToken(string $token): void;

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string;
}
