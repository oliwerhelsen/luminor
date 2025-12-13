<?php

declare(strict_types=1);

namespace Luminor\DDD\Auth;

/**
 * Interface for entities that have roles assigned.
 *
 * Implement this interface on your User entity to enable role-based checks.
 */
interface HasRolesInterface
{
    /**
     * Get all roles for this entity.
     *
     * @return array<RoleInterface>
     */
    public function getRoles(): array;

    /**
     * Check if the entity has a specific role.
     *
     * @param string|RoleInterface $role The role name or instance
     */
    public function hasRole(string|RoleInterface $role): bool;

    /**
     * Get all role names as an array of strings.
     *
     * @return array<string>
     */
    public function getRoleNames(): array;
}
