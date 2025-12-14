<?php

declare(strict_types=1);

namespace Luminor\Auth\Attributes;

use Attribute;

/**
 * Attribute to require specific role(s) for accessing a controller or method.
 *
 * Can be applied to classes (controllers) or methods (actions).
 * When applied to a class, all methods inherit the role requirement.
 *
 * @example
 * #[RequireRole('admin')]
 * class AdminController { }
 *
 * @example
 * #[RequireRole(['editor', 'admin'], RequireRole::MODE_ANY)]
 * public function publish() { }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequireRole
{
    /**
     * All specified roles are required.
     */
    public const MODE_ALL = 'all';

    /**
     * Any one of the specified roles is sufficient.
     */
    public const MODE_ANY = 'any';

    /** @var array<string> */
    public readonly array $roles;

    /**
     * @param string|array<string> $roles The required role(s)
     * @param string $mode The check mode: 'all' (default) or 'any'
     * @param string|null $message Custom error message when authorization fails
     */
    public function __construct(
        string|array $roles,
        public readonly string $mode = self::MODE_ANY,
        public readonly ?string $message = null
    ) {
        $this->roles = is_array($roles) ? $roles : [$roles];
    }

    /**
     * Check if all roles are required.
     */
    public function requiresAll(): bool
    {
        return $this->mode === self::MODE_ALL;
    }

    /**
     * Check if any role is sufficient.
     */
    public function requiresAny(): bool
    {
        return $this->mode === self::MODE_ANY;
    }
}
