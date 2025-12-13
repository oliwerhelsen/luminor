<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Multitenancy;

use Luminor\DDD\Multitenancy\TenantContext;
use Luminor\DDD\Multitenancy\TenantInterface;
use Luminor\DDD\Multitenancy\TenantNotResolvedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TenantContextTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    public function testSetAndGetTenant(): void
    {
        $tenant = $this->createTenant('tenant-1', 'acme');

        TenantContext::setTenant($tenant);

        $this->assertSame($tenant, TenantContext::getTenant());
    }

    public function testGetTenantReturnsNullWhenNotSet(): void
    {
        $this->assertNull(TenantContext::getTenant());
    }

    public function testHasTenantReturnsTrueWhenSet(): void
    {
        TenantContext::setTenant($this->createTenant('tenant-1', 'acme'));

        $this->assertTrue(TenantContext::hasTenant());
    }

    public function testHasTenantReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(TenantContext::hasTenant());
    }

    public function testGetTenantOrFailReturnsWhenSet(): void
    {
        $tenant = $this->createTenant('tenant-1', 'acme');
        TenantContext::setTenant($tenant);

        $this->assertSame($tenant, TenantContext::getTenantOrFail());
    }

    public function testGetTenantOrFailThrowsWhenNotSet(): void
    {
        $this->expectException(TenantNotResolvedException::class);

        TenantContext::getTenantOrFail();
    }

    public function testClearRemovesTenant(): void
    {
        TenantContext::setTenant($this->createTenant('tenant-1', 'acme'));
        TenantContext::clear();

        $this->assertNull(TenantContext::getTenant());
        $this->assertFalse(TenantContext::hasTenant());
    }

    public function testGetTenantIdReturnsIdWhenSet(): void
    {
        TenantContext::setTenant($this->createTenant('tenant-123', 'acme'));

        $this->assertSame('tenant-123', TenantContext::getTenantId());
    }

    public function testGetTenantIdReturnsNullWhenNotSet(): void
    {
        $this->assertNull(TenantContext::getTenantId());
    }

    public function testRunAsExecutesWithTenantContext(): void
    {
        $tenant = $this->createTenant('tenant-1', 'acme');

        $result = TenantContext::runAs($tenant, function () {
            return TenantContext::getTenantId();
        });

        $this->assertSame('tenant-1', $result);
        // Context should be cleared after runAs
        $this->assertNull(TenantContext::getTenant());
    }

    public function testRunAsRestoresPreviousTenant(): void
    {
        $tenant1 = $this->createTenant('tenant-1', 'acme');
        $tenant2 = $this->createTenant('tenant-2', 'beta');

        TenantContext::setTenant($tenant1);

        TenantContext::runAs($tenant2, function () use ($tenant2) {
            $this->assertSame($tenant2, TenantContext::getTenant());
        });

        $this->assertSame($tenant1, TenantContext::getTenant());
    }

    public function testRunAsRestoresPreviousTenantOnException(): void
    {
        $tenant1 = $this->createTenant('tenant-1', 'acme');
        $tenant2 = $this->createTenant('tenant-2', 'beta');

        TenantContext::setTenant($tenant1);

        try {
            TenantContext::runAs($tenant2, function () {
                throw new RuntimeException('Test exception');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertSame($tenant1, TenantContext::getTenant());
    }

    public function testRunWithoutTenantClearsContext(): void
    {
        $tenant = $this->createTenant('tenant-1', 'acme');
        TenantContext::setTenant($tenant);

        $result = TenantContext::runWithoutTenant(function () {
            return TenantContext::getTenant();
        });

        $this->assertNull($result);
        // Context should be restored after runWithoutTenant
        $this->assertSame($tenant, TenantContext::getTenant());
    }

    public function testRunWithoutTenantRestoresOnException(): void
    {
        $tenant = $this->createTenant('tenant-1', 'acme');
        TenantContext::setTenant($tenant);

        try {
            TenantContext::runWithoutTenant(function () {
                throw new RuntimeException('Test exception');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertSame($tenant, TenantContext::getTenant());
    }

    private function createTenant(string $id, string $slug): TenantInterface
    {
        return new class ($id, $slug) implements TenantInterface {
            public function __construct(
                private readonly string $id,
                private readonly string $slug,
            ) {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getSlug(): string
            {
                return $this->slug;
            }

            public function getName(): string
            {
                return ucfirst($this->slug);
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
