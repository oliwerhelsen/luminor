<?php

declare(strict_types=1);

namespace Luminor\Auth;

/**
 * Service for handling authorization checks.
 *
 * This service provides methods to check permissions, roles, and policies
 * for the current or specified user.
 */
final class AuthorizationService
{
    /** @var array<class-string, PolicyInterface> */
    private array $policies = [];

    /** @var callable|null */
    private $userResolver = null;

    /** @var callable|null */
    private $superAdminChecker = null;

    /**
     * Register a policy for a resource class.
     *
     * @param class-string $resourceClass The resource class this policy applies to
     * @param PolicyInterface $policy The policy instance
     * @return $this
     */
    public function registerPolicy(string $resourceClass, PolicyInterface $policy): self
    {
        $this->policies[$resourceClass] = $policy;
        return $this;
    }

    /**
     * Set the user resolver callback.
     *
     * @param callable(): ?AuthenticatableInterface $resolver
     * @return $this
     */
    public function setUserResolver(callable $resolver): self
    {
        $this->userResolver = $resolver;
        return $this;
    }

    /**
     * Set a callback to check if a user is a super admin.
     *
     * Super admins bypass all authorization checks.
     *
     * @param callable(AuthenticatableInterface): bool $checker
     * @return $this
     */
    public function setSuperAdminChecker(callable $checker): self
    {
        $this->superAdminChecker = $checker;
        return $this;
    }

    /**
     * Get the current authenticated user.
     */
    public function getUser(): ?AuthenticatableInterface
    {
        if ($this->userResolver === null) {
            return CurrentUser::get();
        }

        return ($this->userResolver)();
    }

    /**
     * Check if the current user has a specific permission.
     *
     * @param string|PermissionInterface $permission The permission to check
     * @param AuthenticatableInterface|null $user Optional user to check (defaults to current user)
     */
    public function hasPermission(string|PermissionInterface $permission, ?AuthenticatableInterface $user = null): bool
    {
        $user = $user ?? $this->getUser();

        if ($user === null) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $permissionName = $permission instanceof PermissionInterface
            ? $permission->getName()
            : $permission;

        // Check if user implements HasPermissions interface
        if ($user instanceof HasPermissionsInterface) {
            return $user->hasPermission($permissionName);
        }

        return false;
    }

    /**
     * Check if the current user has a specific role.
     *
     * @param string|RoleInterface $role The role to check
     * @param AuthenticatableInterface|null $user Optional user to check (defaults to current user)
     */
    public function hasRole(string|RoleInterface $role, ?AuthenticatableInterface $user = null): bool
    {
        $user = $user ?? $this->getUser();

        if ($user === null) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $roleName = $role instanceof RoleInterface
            ? $role->getName()
            : $role;

        // Check if user implements HasRoles interface
        if ($user instanceof HasRolesInterface) {
            return $user->hasRole($roleName);
        }

        return false;
    }

    /**
     * Check if the current user has any of the given permissions.
     *
     * @param array<string|PermissionInterface> $permissions
     */
    public function hasAnyPermission(array $permissions, ?AuthenticatableInterface $user = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current user has all of the given permissions.
     *
     * @param array<string|PermissionInterface> $permissions
     */
    public function hasAllPermissions(array $permissions, ?AuthenticatableInterface $user = null): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the current user has any of the given roles.
     *
     * @param array<string|RoleInterface> $roles
     */
    public function hasAnyRole(array $roles, ?AuthenticatableInterface $user = null): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user is authorized to perform an ability on a resource.
     *
     * @param string $ability The ability to check (e.g., "view", "update", "delete")
     * @param mixed $resource The resource or resource class
     * @param AuthenticatableInterface|null $user Optional user to check (defaults to current user)
     */
    public function can(string $ability, mixed $resource = null, ?AuthenticatableInterface $user = null): bool
    {
        $user = $user ?? $this->getUser();

        if ($user === null) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $policy = $this->getPolicyFor($resource);

        if ($policy === null) {
            return false;
        }

        // Check "before" hook if available
        if ($policy instanceof AbstractPolicy) {
            $beforeResult = $policy->before($user, $ability);
            if ($beforeResult !== null) {
                return $beforeResult;
            }
        }

        // Call the ability method on the policy
        if (!method_exists($policy, $ability)) {
            return false;
        }

        return $resource !== null
            ? $policy->$ability($user, $resource)
            : $policy->$ability($user);
    }

    /**
     * Check if the user cannot perform an ability on a resource.
     */
    public function cannot(string $ability, mixed $resource = null, ?AuthenticatableInterface $user = null): bool
    {
        return !$this->can($ability, $resource, $user);
    }

    /**
     * Authorize an ability or throw an exception.
     *
     * @throws AuthorizationException If the user is not authorized
     */
    public function authorize(string $ability, mixed $resource = null, ?AuthenticatableInterface $user = null): void
    {
        if ($this->cannot($ability, $resource, $user)) {
            throw AuthorizationException::forAbility($ability, $resource);
        }
    }

    /**
     * Require a permission or throw an exception.
     *
     * @throws AuthorizationException If the user doesn't have the permission
     */
    public function requirePermission(string|PermissionInterface $permission, ?AuthenticatableInterface $user = null): void
    {
        if (!$this->hasPermission($permission, $user)) {
            $permissionName = $permission instanceof PermissionInterface
                ? $permission->getName()
                : $permission;
            throw AuthorizationException::missingPermission($permissionName);
        }
    }

    /**
     * Require a role or throw an exception.
     *
     * @throws AuthorizationException If the user doesn't have the role
     */
    public function requireRole(string|RoleInterface $role, ?AuthenticatableInterface $user = null): void
    {
        if (!$this->hasRole($role, $user)) {
            $roleName = $role instanceof RoleInterface
                ? $role->getName()
                : $role;
            throw AuthorizationException::missingRole($roleName);
        }
    }

    /**
     * Get the policy for a given resource.
     */
    private function getPolicyFor(mixed $resource): ?PolicyInterface
    {
        if ($resource === null) {
            return null;
        }

        $class = is_object($resource) ? get_class($resource) : $resource;

        if (!is_string($class)) {
            return null;
        }

        // Direct match
        if (isset($this->policies[$class])) {
            return $this->policies[$class];
        }

        // Check parent classes
        foreach ($this->policies as $policyClass => $policy) {
            if (is_a($class, $policyClass, true)) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * Check if a user is a super admin.
     */
    private function isSuperAdmin(AuthenticatableInterface $user): bool
    {
        if ($this->superAdminChecker === null) {
            return false;
        }

        return ($this->superAdminChecker)($user);
    }
}
