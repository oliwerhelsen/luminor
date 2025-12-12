<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Auth\AbstractPolicy;
use Lumina\DDD\Auth\AuthenticatableInterface;
use Lumina\DDD\Auth\AuthorizationException;
use Lumina\DDD\Auth\AuthorizationService;
use Lumina\DDD\Auth\CurrentUser;
use Lumina\DDD\Auth\HasPermissionsInterface;
use Lumina\DDD\Auth\HasRolesInterface;
use Lumina\DDD\Auth\PermissionInterface;
use Lumina\DDD\Auth\RoleInterface;

final class AuthorizationServiceTest extends TestCase
{
    private AuthorizationService $service;

    protected function setUp(): void
    {
        $this->service = new AuthorizationService();
    }

    protected function tearDown(): void
    {
        CurrentUser::clear();
    }

    public function testHasPermissionReturnsFalseWhenNoUser(): void
    {
        $this->assertFalse($this->service->hasPermission('posts.create'));
    }

    public function testHasPermissionChecksUserPermissions(): void
    {
        $user = $this->createUserWithPermissions(['posts.create', 'posts.view']);
        CurrentUser::set($user);

        $this->assertTrue($this->service->hasPermission('posts.create'));
        $this->assertTrue($this->service->hasPermission('posts.view'));
        $this->assertFalse($this->service->hasPermission('posts.delete'));
    }

    public function testHasPermissionReturnsTrueForSuperAdmin(): void
    {
        $user = $this->createUserWithPermissions([]);
        CurrentUser::set($user);

        $this->service->setSuperAdminChecker(fn($u) => true);

        $this->assertTrue($this->service->hasPermission('any.permission'));
    }

    public function testHasRoleReturnsFalseWhenNoUser(): void
    {
        $this->assertFalse($this->service->hasRole('admin'));
    }

    public function testHasRoleChecksUserRoles(): void
    {
        $user = $this->createUserWithRoles(['editor', 'viewer']);
        CurrentUser::set($user);

        $this->assertTrue($this->service->hasRole('editor'));
        $this->assertTrue($this->service->hasRole('viewer'));
        $this->assertFalse($this->service->hasRole('admin'));
    }

    public function testHasAnyPermissionReturnsTrue(): void
    {
        $user = $this->createUserWithPermissions(['posts.view']);
        CurrentUser::set($user);

        $this->assertTrue($this->service->hasAnyPermission(['posts.view', 'posts.delete']));
    }

    public function testHasAnyPermissionReturnsFalse(): void
    {
        $user = $this->createUserWithPermissions(['posts.view']);
        CurrentUser::set($user);

        $this->assertFalse($this->service->hasAnyPermission(['posts.create', 'posts.delete']));
    }

    public function testHasAllPermissionsReturnsTrue(): void
    {
        $user = $this->createUserWithPermissions(['posts.create', 'posts.view']);
        CurrentUser::set($user);

        $this->assertTrue($this->service->hasAllPermissions(['posts.create', 'posts.view']));
    }

    public function testHasAllPermissionsReturnsFalse(): void
    {
        $user = $this->createUserWithPermissions(['posts.view']);
        CurrentUser::set($user);

        $this->assertFalse($this->service->hasAllPermissions(['posts.create', 'posts.view']));
    }

    public function testHasAnyRoleReturnsTrue(): void
    {
        $user = $this->createUserWithRoles(['viewer']);
        CurrentUser::set($user);

        $this->assertTrue($this->service->hasAnyRole(['admin', 'viewer']));
    }

    public function testHasAnyRoleReturnsFalse(): void
    {
        $user = $this->createUserWithRoles(['viewer']);
        CurrentUser::set($user);

        $this->assertFalse($this->service->hasAnyRole(['admin', 'editor']));
    }

    public function testCanUsesRegisteredPolicy(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $policy = new class extends AbstractPolicy {
            public function view(AuthenticatableInterface $user, mixed $resource): bool
            {
                return true;
            }

            public function delete(AuthenticatableInterface $user, mixed $resource): bool
            {
                return false;
            }
        };

        $this->service->registerPolicy(TestResource::class, $policy);

        $resource = new TestResource();

        $this->assertTrue($this->service->can('view', $resource));
        $this->assertFalse($this->service->can('delete', $resource));
    }

    public function testCanReturnsFalseWhenNoPolicyRegistered(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $this->assertFalse($this->service->can('view', new TestResource()));
    }

    public function testCanReturnsFalseWhenNoUser(): void
    {
        $policy = new class extends AbstractPolicy {
            public function view(AuthenticatableInterface $user, mixed $resource): bool
            {
                return true;
            }
        };

        $this->service->registerPolicy(TestResource::class, $policy);

        $this->assertFalse($this->service->can('view', new TestResource()));
    }

    public function testCanReturnsTrueForSuperAdmin(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $this->service->setSuperAdminChecker(fn($u) => true);

        // Even without a policy, super admin should have access
        $this->assertTrue($this->service->can('anything', new TestResource()));
    }

    public function testCannotIsInverseOfCan(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $policy = new class extends AbstractPolicy {
            public function view(AuthenticatableInterface $user, mixed $resource): bool
            {
                return true;
            }
        };

        $this->service->registerPolicy(TestResource::class, $policy);

        $resource = new TestResource();

        $this->assertFalse($this->service->cannot('view', $resource));
        $this->assertTrue($this->service->cannot('delete', $resource));
    }

    public function testAuthorizeThrowsWhenNotAuthorized(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $policy = new class extends AbstractPolicy {
            public function view(AuthenticatableInterface $user, mixed $resource): bool
            {
                return false;
            }
        };

        $this->service->registerPolicy(TestResource::class, $policy);

        $this->expectException(AuthorizationException::class);
        $this->service->authorize('view', new TestResource());
    }

    public function testAuthorizeDoesNotThrowWhenAuthorized(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $policy = new class extends AbstractPolicy {
            public function view(AuthenticatableInterface $user, mixed $resource): bool
            {
                return true;
            }
        };

        $this->service->registerPolicy(TestResource::class, $policy);

        $this->service->authorize('view', new TestResource());
        $this->assertTrue(true); // No exception thrown
    }

    public function testRequirePermissionThrowsWhenMissing(): void
    {
        $user = $this->createUserWithPermissions([]);
        CurrentUser::set($user);

        $this->expectException(AuthorizationException::class);
        $this->service->requirePermission('posts.create');
    }

    public function testRequirePermissionDoesNotThrowWhenPresent(): void
    {
        $user = $this->createUserWithPermissions(['posts.create']);
        CurrentUser::set($user);

        $this->service->requirePermission('posts.create');
        $this->assertTrue(true); // No exception thrown
    }

    public function testRequireRoleThrowsWhenMissing(): void
    {
        $user = $this->createUserWithRoles([]);
        CurrentUser::set($user);

        $this->expectException(AuthorizationException::class);
        $this->service->requireRole('admin');
    }

    public function testRequireRoleDoesNotThrowWhenPresent(): void
    {
        $user = $this->createUserWithRoles(['admin']);
        CurrentUser::set($user);

        $this->service->requireRole('admin');
        $this->assertTrue(true); // No exception thrown
    }

    public function testPolicyBeforeHookCanGrantAccess(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $policy = new class extends AbstractPolicy {
            public function before(AuthenticatableInterface $user, string $ability): ?bool
            {
                return true; // Grant all access
            }

            public function view(AuthenticatableInterface $user, mixed $resource): bool
            {
                return false; // This won't be called
            }
        };

        $this->service->registerPolicy(TestResource::class, $policy);

        $this->assertTrue($this->service->can('view', new TestResource()));
    }

    public function testPolicyBeforeHookCanDenyAccess(): void
    {
        $user = $this->createUser();
        CurrentUser::set($user);

        $policy = new class extends AbstractPolicy {
            public function before(AuthenticatableInterface $user, string $ability): ?bool
            {
                return false; // Deny all access
            }

            public function view(AuthenticatableInterface $user, mixed $resource): bool
            {
                return true; // This won't be called
            }
        };

        $this->service->registerPolicy(TestResource::class, $policy);

        $this->assertFalse($this->service->can('view', new TestResource()));
    }

    public function testCustomUserResolver(): void
    {
        $user = $this->createUserWithPermissions(['posts.create']);

        $this->service->setUserResolver(fn() => $user);

        $this->assertTrue($this->service->hasPermission('posts.create'));
    }

    private function createUser(): AuthenticatableInterface
    {
        return new class implements AuthenticatableInterface {
            public function getAuthIdentifier(): string
            {
                return 'user-1';
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthPassword(): ?string
            {
                return null;
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken(string $token): void
            {
            }

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };
    }

    /**
     * @param array<string> $permissions
     */
    private function createUserWithPermissions(array $permissions): AuthenticatableInterface
    {
        return new class($permissions) implements AuthenticatableInterface, HasPermissionsInterface {
            /**
             * @param array<string> $permissions
             */
            public function __construct(private readonly array $permissions)
            {
            }

            public function getAuthIdentifier(): string
            {
                return 'user-1';
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthPassword(): ?string
            {
                return null;
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken(string $token): void
            {
            }

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }

            public function getPermissions(): array
            {
                return [];
            }

            public function hasPermission(string|PermissionInterface $permission): bool
            {
                $name = $permission instanceof PermissionInterface ? $permission->getName() : $permission;
                return in_array($name, $this->permissions, true);
            }

            public function getPermissionNames(): array
            {
                return $this->permissions;
            }
        };
    }

    /**
     * @param array<string> $roles
     */
    private function createUserWithRoles(array $roles): AuthenticatableInterface
    {
        return new class($roles) implements AuthenticatableInterface, HasRolesInterface {
            /**
             * @param array<string> $roles
             */
            public function __construct(private readonly array $roles)
            {
            }

            public function getAuthIdentifier(): string
            {
                return 'user-1';
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthPassword(): ?string
            {
                return null;
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken(string $token): void
            {
            }

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function hasRole(string|RoleInterface $role): bool
            {
                $name = $role instanceof RoleInterface ? $role->getName() : $role;
                return in_array($name, $this->roles, true);
            }

            public function getRoleNames(): array
            {
                return $this->roles;
            }
        };
    }
}

final class TestResource
{
}
