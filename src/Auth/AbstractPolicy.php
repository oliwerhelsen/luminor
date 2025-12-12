<?php

declare(strict_types=1);

namespace Lumina\DDD\Auth;

/**
 * Abstract base class for authorization policies.
 *
 * Provides default implementations that deny all actions.
 * Override specific methods to grant access.
 */
abstract class AbstractPolicy implements PolicyInterface
{
    /**
     * Perform a pre-authorization check before any other method.
     *
     * Return true to allow all actions, false to deny all actions,
     * or null to fall through to the specific policy method.
     *
     * This is useful for super-admin bypass logic.
     */
    public function before(AuthenticatableInterface $user, string $ability): ?bool
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function viewAny(AuthenticatableInterface $user): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function view(AuthenticatableInterface $user, mixed $resource): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function create(AuthenticatableInterface $user): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function update(AuthenticatableInterface $user, mixed $resource): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function delete(AuthenticatableInterface $user, mixed $resource): bool
    {
        return false;
    }
}
