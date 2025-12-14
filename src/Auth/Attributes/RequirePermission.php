<?php

declare(strict_types=1);

namespace Luminor\Auth\Attributes;

use Attribute;

/**
 * Attribute to require specific permission(s) for accessing a controller or method.
 *
 * Can be applied to classes (controllers) or methods (actions).
 * When applied to a class, all methods inherit the permission requirement.
 *
 * @example
 * #[RequirePermission('users.view')]
 * class UserController { }
 *
 * @example
 * #[RequirePermission(['posts.create', 'posts.update'], RequirePermission::MODE_ANY)]
 * public function store() { }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequirePermission
{
    /**
     * All specified permissions are required.
     */
    public const MODE_ALL = 'all';

    /**
     * Any one of the specified permissions is sufficient.
     */
    public const MODE_ANY = 'any';

    /** @var array<string> */
    public readonly array $permissions;

    /**
     * @param string|array<string> $permissions The required permission(s)
     * @param string $mode The check mode: 'all' (default) or 'any'
     * @param string|null $message Custom error message when authorization fails
     */
    public function __construct(
        string|array $permissions,
        public readonly string $mode = self::MODE_ALL,
        public readonly ?string $message = null
    ) {
        $this->permissions = is_array($permissions) ? $permissions : [$permissions];
    }

    /**
     * Check if all permissions are required.
     */
    public function requiresAll(): bool
    {
        return $this->mode === self::MODE_ALL;
    }

    /**
     * Check if any permission is sufficient.
     */
    public function requiresAny(): bool
    {
        return $this->mode === self::MODE_ANY;
    }
}
