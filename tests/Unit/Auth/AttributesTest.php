<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Auth;

use Attribute;
use Luminor\DDD\Auth\Attributes\RequireAuth;
use Luminor\DDD\Auth\Attributes\RequirePermission;
use Luminor\DDD\Auth\Attributes\RequireRole;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AttributesTest extends TestCase
{
    public function testRequirePermissionWithSinglePermission(): void
    {
        $attr = new RequirePermission('posts.create');

        $this->assertSame(['posts.create'], $attr->permissions);
        $this->assertSame(RequirePermission::MODE_ALL, $attr->mode);
        $this->assertTrue($attr->requiresAll());
        $this->assertFalse($attr->requiresAny());
    }

    public function testRequirePermissionWithMultiplePermissions(): void
    {
        $attr = new RequirePermission(['posts.create', 'posts.update']);

        $this->assertSame(['posts.create', 'posts.update'], $attr->permissions);
    }

    public function testRequirePermissionWithAnyMode(): void
    {
        $attr = new RequirePermission(['posts.create', 'posts.update'], RequirePermission::MODE_ANY);

        $this->assertSame(RequirePermission::MODE_ANY, $attr->mode);
        $this->assertFalse($attr->requiresAll());
        $this->assertTrue($attr->requiresAny());
    }

    public function testRequirePermissionWithCustomMessage(): void
    {
        $attr = new RequirePermission('posts.create', message: 'Custom error');

        $this->assertSame('Custom error', $attr->message);
    }

    public function testRequireRoleWithSingleRole(): void
    {
        $attr = new RequireRole('admin');

        $this->assertSame(['admin'], $attr->roles);
        // Default mode for roles is ANY (usually you need one of multiple roles)
        $this->assertSame(RequireRole::MODE_ANY, $attr->mode);
        $this->assertTrue($attr->requiresAny());
        $this->assertFalse($attr->requiresAll());
    }

    public function testRequireRoleWithMultipleRoles(): void
    {
        $attr = new RequireRole(['admin', 'editor']);

        $this->assertSame(['admin', 'editor'], $attr->roles);
    }

    public function testRequireRoleWithAllMode(): void
    {
        $attr = new RequireRole(['admin', 'editor'], RequireRole::MODE_ALL);

        $this->assertSame(RequireRole::MODE_ALL, $attr->mode);
        $this->assertTrue($attr->requiresAll());
        $this->assertFalse($attr->requiresAny());
    }

    public function testRequireRoleWithCustomMessage(): void
    {
        $attr = new RequireRole('admin', message: 'Admin access required');

        $this->assertSame('Admin access required', $attr->message);
    }

    public function testRequireAuthDefaultMessage(): void
    {
        $attr = new RequireAuth();

        $this->assertNull($attr->message);
    }

    public function testRequireAuthWithCustomMessage(): void
    {
        $attr = new RequireAuth('Please log in first');

        $this->assertSame('Please log in first', $attr->message);
    }

    public function testRequirePermissionIsRepeatable(): void
    {
        $reflection = new ReflectionClass(RequirePermission::class);
        $attributes = $reflection->getAttributes();

        $this->assertCount(1, $attributes);
        $this->assertSame(Attribute::class, $attributes[0]->getName());

        $attrInstance = $attributes[0]->newInstance();
        $this->assertTrue(($attrInstance->flags & Attribute::IS_REPEATABLE) !== 0);
    }

    public function testRequireRoleIsRepeatable(): void
    {
        $reflection = new ReflectionClass(RequireRole::class);
        $attributes = $reflection->getAttributes();

        $this->assertCount(1, $attributes);
        $this->assertSame(Attribute::class, $attributes[0]->getName());

        $attrInstance = $attributes[0]->newInstance();
        $this->assertTrue(($attrInstance->flags & Attribute::IS_REPEATABLE) !== 0);
    }
}
