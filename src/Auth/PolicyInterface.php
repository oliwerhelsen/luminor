<?php

declare(strict_types=1);

namespace Luminor\Auth;

/**
 * Interface for authorization policies.
 *
 * Policies encapsulate authorization logic for specific resources or actions.
 * They determine whether a user is allowed to perform certain operations
 * on specific resources, enabling fine-grained access control.
 */
interface PolicyInterface
{
    /**
     * Determine if the user can view any instances of the resource.
     */
    public function viewAny(AuthenticatableInterface $user): bool;

    /**
     * Determine if the user can view the given resource.
     *
     * @param AuthenticatableInterface $user The user making the request
     * @param mixed $resource The resource being accessed
     */
    public function view(AuthenticatableInterface $user, mixed $resource): bool;

    /**
     * Determine if the user can create new instances of the resource.
     */
    public function create(AuthenticatableInterface $user): bool;

    /**
     * Determine if the user can update the given resource.
     *
     * @param AuthenticatableInterface $user The user making the request
     * @param mixed $resource The resource being updated
     */
    public function update(AuthenticatableInterface $user, mixed $resource): bool;

    /**
     * Determine if the user can delete the given resource.
     *
     * @param AuthenticatableInterface $user The user making the request
     * @param mixed $resource The resource being deleted
     */
    public function delete(AuthenticatableInterface $user, mixed $resource): bool;
}
