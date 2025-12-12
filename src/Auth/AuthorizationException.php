<?php

declare(strict_types=1);

namespace Lumina\DDD\Auth;

use RuntimeException;

/**
 * Exception thrown when authorization fails.
 */
final class AuthorizationException extends RuntimeException
{
    public function __construct(
        string $message = 'This action is unauthorized.',
        private readonly ?string $ability = null,
        private readonly mixed $resource = null,
        int $code = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for a denied ability.
     */
    public static function forAbility(string $ability, mixed $resource = null): self
    {
        $resourceType = $resource !== null ? get_debug_type($resource) : null;
        $message = $resourceType !== null
            ? sprintf('You are not authorized to %s this %s.', $ability, $resourceType)
            : sprintf('You are not authorized to perform this action: %s.', $ability);

        return new self($message, $ability, $resource);
    }

    /**
     * Create exception for missing permission.
     */
    public static function missingPermission(string $permission): self
    {
        return new self(
            sprintf('You do not have the required permission: %s.', $permission),
            $permission
        );
    }

    /**
     * Create exception for missing role.
     */
    public static function missingRole(string $role): self
    {
        return new self(
            sprintf('You do not have the required role: %s.', $role),
            $role
        );
    }

    /**
     * Get the ability that was denied.
     */
    public function getAbility(): ?string
    {
        return $this->ability;
    }

    /**
     * Get the resource that was being accessed.
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }
}
