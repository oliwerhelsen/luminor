<?php

declare(strict_types=1);

namespace Lumina\DDD\Auth\Attributes;

use Attribute;

/**
 * Attribute to require authentication for accessing a controller or method.
 *
 * Can be applied to classes (controllers) or methods (actions).
 * When applied to a class, all methods require authentication.
 *
 * @example
 * #[RequireAuth]
 * class ProfileController { }
 *
 * @example
 * #[RequireAuth(message: 'Please log in to access your profile')]
 * public function show() { }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RequireAuth
{
    /**
     * @param string|null $message Custom error message when authentication is required
     */
    public function __construct(
        public readonly ?string $message = null
    ) {
    }
}
