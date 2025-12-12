<?php

declare(strict_types=1);

namespace Lumina\DDD\Auth;

/**
 * Interface representing a permission in the authorization system.
 *
 * Permissions are atomic units of authorization that can be granted
 * to users directly or through roles.
 */
interface PermissionInterface
{
    /**
     * Get the unique identifier for this permission.
     *
     * @return string|int The permission identifier
     */
    public function getId(): string|int;

    /**
     * Get the permission's unique name/key.
     *
     * This is typically used for programmatic checks (e.g., "users.create", "posts.delete").
     */
    public function getName(): string;

    /**
     * Get a human-readable description of the permission.
     */
    public function getDescription(): string;

    /**
     * Get the group/category this permission belongs to.
     *
     * Used for organizing permissions in UIs (e.g., "Users", "Posts", "Settings").
     *
     * @return string|null The group name or null if ungrouped
     */
    public function getGroup(): ?string;
}
