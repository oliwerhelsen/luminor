<?php

declare(strict_types=1);

namespace Luminor\DDD\Auth;

/**
 * Static context holder for the currently authenticated user.
 *
 * This class provides a convenient way to access the current user
 * throughout the application without dependency injection.
 */
final class CurrentUser
{
    private static ?AuthenticatableInterface $user = null;

    /**
     * Set the current authenticated user.
     */
    public static function set(?AuthenticatableInterface $user): void
    {
        self::$user = $user;
    }

    /**
     * Get the current authenticated user.
     */
    public static function get(): ?AuthenticatableInterface
    {
        return self::$user;
    }

    /**
     * Get the current authenticated user or throw an exception.
     *
     * @throws AuthenticationException If no user is authenticated
     */
    public static function getOrFail(): AuthenticatableInterface
    {
        if (self::$user === null) {
            throw new AuthenticationException('No authenticated user.');
        }

        return self::$user;
    }

    /**
     * Check if a user is currently authenticated.
     */
    public static function isAuthenticated(): bool
    {
        return self::$user !== null;
    }

    /**
     * Check if no user is currently authenticated.
     */
    public static function isGuest(): bool
    {
        return self::$user === null;
    }

    /**
     * Clear the current user context.
     */
    public static function clear(): void
    {
        self::$user = null;
    }

    /**
     * Get the current user's ID.
     *
     * @return string|int|null The user ID or null if not authenticated
     */
    public static function getId(): string|int|null
    {
        return self::$user?->getAuthIdentifier();
    }

    /**
     * Execute a callback as a specific user.
     *
     * @template T
     * @param AuthenticatableInterface $user The user to act as
     * @param callable(): T $callback The callback to execute
     * @return T The result of the callback
     */
    public static function actingAs(AuthenticatableInterface $user, callable $callback): mixed
    {
        $previousUser = self::$user;
        self::$user = $user;

        try {
            return $callback();
        } finally {
            self::$user = $previousUser;
        }
    }

    /**
     * Execute a callback as a guest (no authenticated user).
     *
     * @template T
     * @param callable(): T $callback The callback to execute
     * @return T The result of the callback
     */
    public static function actingAsGuest(callable $callback): mixed
    {
        $previousUser = self::$user;
        self::$user = null;

        try {
            return $callback();
        } finally {
            self::$user = $previousUser;
        }
    }

    /**
     * Check if the current user has a specific permission.
     *
     * @param string|PermissionInterface $permission The permission to check
     */
    public static function hasPermission(string|PermissionInterface $permission): bool
    {
        if (self::$user === null) {
            return false;
        }

        if (!self::$user instanceof HasPermissionsInterface) {
            return false;
        }

        return self::$user->hasPermission($permission);
    }

    /**
     * Check if the current user has a specific role.
     *
     * @param string|RoleInterface $role The role to check
     */
    public static function hasRole(string|RoleInterface $role): bool
    {
        if (self::$user === null) {
            return false;
        }

        if (!self::$user instanceof HasRolesInterface) {
            return false;
        }

        return self::$user->hasRole($role);
    }
}
