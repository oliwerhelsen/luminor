<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Multitenancy;

use PHPUnit\Framework\TestCase;
use Luminor\DDD\Multitenancy\TenantAware;
use Luminor\DDD\Multitenancy\TenantContext;
use Luminor\DDD\Multitenancy\TenantInterface;
use Luminor\DDD\Multitenancy\TenantNotResolvedException;

final class TenantAwareTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    public function testGetTenantIdReturnsNullByDefault(): void
    {
        $entity = new TenantAwareEntity();

        $this->assertNull($entity->getTenantId());
    }

    public function testSetAndGetTenantId(): void
    {
        $entity = new TenantAwareEntity();
        $entity->setTenantId('tenant-123');

        $this->assertSame('tenant-123', $entity->getTenantId());
    }

    public function testSetTenantIdWithInteger(): void
    {
        $entity = new TenantAwareEntity();
        $entity->setTenantId(123);

        $this->assertSame(123, $entity->getTenantId());
    }

    public function testHasTenantReturnsFalseWhenNotSet(): void
    {
        $entity = new TenantAwareEntity();

        $this->assertFalse($entity->hasTenant());
    }

    public function testHasTenantReturnsTrueWhenSet(): void
    {
        $entity = new TenantAwareEntity();
        $entity->setTenantId('tenant-123');

        $this->assertTrue($entity->hasTenant());
    }

    public function testBelongsToTenantReturnsTrue(): void
    {
        $entity = new TenantAwareEntity();
        $entity->setTenantId('tenant-123');

        $this->assertTrue($entity->belongsToTenant('tenant-123'));
    }

    public function testBelongsToTenantReturnsFalse(): void
    {
        $entity = new TenantAwareEntity();
        $entity->setTenantId('tenant-123');

        $this->assertFalse($entity->belongsToTenant('other-tenant'));
    }

    public function testAssignToCurrentTenant(): void
    {
        $tenant = $this->createTenant('tenant-456');
        TenantContext::setTenant($tenant);

        $entity = new TenantAwareEntity();
        $entity->assignToCurrentTenant();

        $this->assertSame('tenant-456', $entity->getTenantId());
    }

    public function testAssignToCurrentTenantThrowsWhenNoContext(): void
    {
        $entity = new TenantAwareEntity();

        $this->expectException(TenantNotResolvedException::class);
        $entity->assignToCurrentTenant();
    }

    public function testSetTenantIdReturnsThis(): void
    {
        $entity = new TenantAwareEntity();
        $result = $entity->setTenantId('tenant-123');

        $this->assertSame($entity, $result);
    }

    public function testAssignToCurrentTenantReturnsThis(): void
    {
        $tenant = $this->createTenant('tenant-456');
        TenantContext::setTenant($tenant);

        $entity = new TenantAwareEntity();
        $result = $entity->assignToCurrentTenant();

        $this->assertSame($entity, $result);
    }

    private function createTenant(string $id): TenantInterface
    {
        return new class($id) implements TenantInterface {
            public function __construct(private readonly string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getSlug(): string
            {
                return 'slug';
            }

            public function getName(): string
            {
                return 'Test';
            }

            public function isActive(): bool
            {
                return true;
            }

            public function getConfig(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function getAllConfig(): array
            {
                return [];
            }
        };
    }
}

final class TenantAwareEntity
{
    use TenantAware;
}
