<?php

declare(strict_types=1);

namespace Lumina\DDD\Auth;

/**
 * Interface representing a role in the authorization system.
 *
 * Roles are collections of permissions that can be assigned to users.
 * They provide a convenient way to group related permissions.
 */
interface RoleInterface
{
    /**
     * Get the unique identifier for this role.
     *
     * @return string|int The role identifier
     */
    public function getId(): string|int;

    /**
     * Get the role's unique name/key.
     *
     * This is typically used for programmatic checks (e.g., "admin", "editor", "viewer").
     */
    public function getName(): string;

    /**
     * Get a human-readable display name for the role.
     */
    public function getDisplayName(): string;

    /**
     * Get a description of this role.
     */
    public function getDescription(): string;

    /**
     * Get all permissions associated with this role.
     *
     * @return array<PermissionInterface>
     */
    public function getPermissions(): array;

    /**
     * Check if this role has a specific permission.
     *
     * @param string|PermissionInterface $permission The permission name or instance
     */
    public function hasPermission(string|PermissionInterface $permission): bool;

    /**
     * Check if this is a system/built-in role that cannot be modified.
     */
    public function isSystem(): bool;
}
