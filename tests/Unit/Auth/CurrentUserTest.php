<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Auth;

use Luminor\DDD\Auth\AuthenticatableInterface;
use Luminor\DDD\Auth\AuthenticationException;
use Luminor\DDD\Auth\CurrentUser;
use Luminor\DDD\Auth\HasPermissionsInterface;
use Luminor\DDD\Auth\HasRolesInterface;
use Luminor\DDD\Auth\PermissionInterface;
use Luminor\DDD\Auth\RoleInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CurrentUserTest extends TestCase
{
    protected function tearDown(): void
    {
        CurrentUser::clear();
    }

    public function testSetAndGetUser(): void
    {
        $user = $this->createUser('user-1');
        CurrentUser::set($user);

        $this->assertSame($user, CurrentUser::get());
    }

    public function testGetReturnsNullWhenNotSet(): void
    {
        $this->assertNull(CurrentUser::get());
    }

    public function testGetOrFailReturnsUserWhenSet(): void
    {
        $user = $this->createUser('user-1');
        CurrentUser::set($user);

        $this->assertSame($user, CurrentUser::getOrFail());
    }

    public function testGetOrFailThrowsWhenNotSet(): void
    {
        $this->expectException(AuthenticationException::class);
        CurrentUser::getOrFail();
    }

    public function testIsAuthenticatedReturnsTrueWhenSet(): void
    {
        CurrentUser::set($this->createUser('user-1'));

        $this->assertTrue(CurrentUser::isAuthenticated());
    }

    public function testIsAuthenticatedReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(CurrentUser::isAuthenticated());
    }

    public function testIsGuestReturnsTrueWhenNotSet(): void
    {
        $this->assertTrue(CurrentUser::isGuest());
    }

    public function testIsGuestReturnsFalseWhenSet(): void
    {
        CurrentUser::set($this->createUser('user-1'));

        $this->assertFalse(CurrentUser::isGuest());
    }

    public function testClearRemovesUser(): void
    {
        CurrentUser::set($this->createUser('user-1'));
        CurrentUser::clear();

        $this->assertNull(CurrentUser::get());
        $this->assertTrue(CurrentUser::isGuest());
    }

    public function testGetIdReturnsUserIdWhenSet(): void
    {
        CurrentUser::set($this->createUser('user-123'));

        $this->assertSame('user-123', CurrentUser::getId());
    }

    public function testGetIdReturnsNullWhenNotSet(): void
    {
        $this->assertNull(CurrentUser::getId());
    }

    public function testActingAsExecutesCallbackAsSpecifiedUser(): void
    {
        $originalUser = $this->createUser('original');
        $actingUser = $this->createUser('acting');
        CurrentUser::set($originalUser);

        $result = CurrentUser::actingAs($actingUser, function () {
            return CurrentUser::getId();
        });

        $this->assertSame('acting', $result);
        $this->assertSame($originalUser, CurrentUser::get());
    }

    public function testActingAsRestoresOriginalUserOnException(): void
    {
        $originalUser = $this->createUser('original');
        $actingUser = $this->createUser('acting');
        CurrentUser::set($originalUser);

        try {
            CurrentUser::actingAs($actingUser, function () {
                throw new RuntimeException('Test');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertSame($originalUser, CurrentUser::get());
    }

    public function testActingAsGuestExecutesCallbackWithoutUser(): void
    {
        $user = $this->createUser('user-1');
        CurrentUser::set($user);

        $result = CurrentUser::actingAsGuest(function () {
            return CurrentUser::isGuest();
        });

        $this->assertTrue($result);
        $this->assertSame($user, CurrentUser::get());
    }

    public function testActingAsGuestRestoresOriginalUserOnException(): void
    {
        $user = $this->createUser('user-1');
        CurrentUser::set($user);

        try {
            CurrentUser::actingAsGuest(function () {
                throw new RuntimeException('Test');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertSame($user, CurrentUser::get());
    }

    public function testHasPermissionReturnsFalseWhenNotAuthenticated(): void
    {
        $this->assertFalse(CurrentUser::hasPermission('some.permission'));
    }

    public function testHasPermissionReturnsFalseWhenUserDoesNotImplementInterface(): void
    {
        CurrentUser::set($this->createUser('user-1'));

        $this->assertFalse(CurrentUser::hasPermission('some.permission'));
    }

    public function testHasPermissionChecksUserPermissions(): void
    {
        $user = $this->createUserWithPermissions('user-1', ['posts.create', 'posts.view']);
        CurrentUser::set($user);

        $this->assertTrue(CurrentUser::hasPermission('posts.create'));
        $this->assertTrue(CurrentUser::hasPermission('posts.view'));
        $this->assertFalse(CurrentUser::hasPermission('posts.delete'));
    }

    public function testHasRoleReturnsFalseWhenNotAuthenticated(): void
    {
        $this->assertFalse(CurrentUser::hasRole('admin'));
    }

    public function testHasRoleReturnsFalseWhenUserDoesNotImplementInterface(): void
    {
        CurrentUser::set($this->createUser('user-1'));

        $this->assertFalse(CurrentUser::hasRole('admin'));
    }

    public function testHasRoleChecksUserRoles(): void
    {
        $user = $this->createUserWithRoles('user-1', ['editor', 'viewer']);
        CurrentUser::set($user);

        $this->assertTrue(CurrentUser::hasRole('editor'));
        $this->assertTrue(CurrentUser::hasRole('viewer'));
        $this->assertFalse(CurrentUser::hasRole('admin'));
    }

    private function createUser(string $id): AuthenticatableInterface
    {
        return new class ($id) implements AuthenticatableInterface {
            public function __construct(private readonly string $id)
            {
            }

            public function getAuthIdentifier(): string
            {
                return $this->id;
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
    private function createUserWithPermissions(string $id, array $permissions): AuthenticatableInterface
    {
        return new class ($id, $permissions) implements AuthenticatableInterface, HasPermissionsInterface {
            /**
             * @param array<string> $permissions
             */
            public function __construct(
                private readonly string $id,
                private readonly array $permissions,
            ) {
            }

            public function getAuthIdentifier(): string
            {
                return $this->id;
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
    private function createUserWithRoles(string $id, array $roles): AuthenticatableInterface
    {
        return new class ($id, $roles) implements AuthenticatableInterface, HasRolesInterface {
            /**
             * @param array<string> $roles
             */
            public function __construct(
                private readonly string $id,
                private readonly array $roles,
            ) {
            }

            public function getAuthIdentifier(): string
            {
                return $this->id;
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
