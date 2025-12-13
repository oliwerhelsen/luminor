<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy;

/**
 * Holds the current tenant context for the application.
 *
 * This class provides a way to access and manage the currently active tenant
 * throughout a request lifecycle. It acts as a thread-local storage for tenant information.
 */
final class TenantContext
{
    private static ?TenantInterface $currentTenant = null;

    /**
     * Set the current tenant.
     */
    public static function setTenant(?TenantInterface $tenant): void
    {
        self::$currentTenant = $tenant;
    }

    /**
     * Get the current tenant.
     *
     * @return TenantInterface|null The current tenant or null if not set
     */
    public static function getTenant(): ?TenantInterface
    {
        return self::$currentTenant;
    }

    /**
     * Get the current tenant or throw an exception.
     *
     * @throws TenantNotResolvedException If no tenant is currently set
     */
    public static function getTenantOrFail(): TenantInterface
    {
        if (self::$currentTenant === null) {
            throw new TenantNotResolvedException('No tenant is currently set in the context.');
        }

        return self::$currentTenant;
    }

    /**
     * Check if a tenant is currently set.
     */
    public static function hasTenant(): bool
    {
        return self::$currentTenant !== null;
    }

    /**
     * Clear the current tenant context.
     */
    public static function clear(): void
    {
        self::$currentTenant = null;
    }

    /**
     * Get the current tenant's ID.
     *
     * @return string|int|null The tenant ID or null if no tenant is set
     */
    public static function getTenantId(): string|int|null
    {
        return self::$currentTenant?->getId();
    }

    /**
     * Execute a callback within a specific tenant context.
     *
     * This method temporarily sets the tenant, executes the callback,
     * and then restores the previous tenant (if any).
     *
     * @template T
     * @param TenantInterface $tenant The tenant to use for the callback
     * @param callable(): T $callback The callback to execute
     * @return T The result of the callback
     */
    public static function runAs(TenantInterface $tenant, callable $callback): mixed
    {
        $previousTenant = self::$currentTenant;
        self::$currentTenant = $tenant;

        try {
            return $callback();
        } finally {
            self::$currentTenant = $previousTenant;
        }
    }

    /**
     * Execute a callback without any tenant context.
     *
     * This method temporarily clears the tenant context, executes the callback,
     * and then restores the previous tenant (if any).
     *
     * @template T
     * @param callable(): T $callback The callback to execute
     * @return T The result of the callback
     */
    public static function runWithoutTenant(callable $callback): mixed
    {
        $previousTenant = self::$currentTenant;
        self::$currentTenant = null;

        try {
            return $callback();
        } finally {
            self::$currentTenant = $previousTenant;
        }
    }
}
