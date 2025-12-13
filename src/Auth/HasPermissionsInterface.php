<?php

declare(strict_types=1);

namespace Luminor\DDD\Auth;

/**
 * Interface for entities that have permissions assigned.
 *
 * Implement this interface on your User entity to enable permission checks.
 */
interface HasPermissionsInterface
{
    /**
     * Get all permissions for this entity.
     *
     * @return array<PermissionInterface>
     */
    public function getPermissions(): array;

    /**
     * Check if the entity has a specific permission.
     *
     * @param string|PermissionInterface $permission The permission name or instance
     */
    public function hasPermission(string|PermissionInterface $permission): bool;

    /**
     * Get all permission names as an array of strings.
     *
     * @return array<string>
     */
    public function getPermissionNames(): array;
}
