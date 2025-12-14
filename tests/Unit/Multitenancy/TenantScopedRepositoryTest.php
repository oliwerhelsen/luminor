<?php

declare(strict_types=1);

namespace Luminor\Tests\Unit\Multitenancy;

use PHPUnit\Framework\TestCase;
use Luminor\Domain\Abstractions\AggregateRoot;
use Luminor\Domain\Repository\Criteria;
use Luminor\Domain\Repository\RepositoryInterface;
use Luminor\Infrastructure\Persistence\InMemory\InMemoryRepository;
use Luminor\Multitenancy\TenantAccessDeniedException;
use Luminor\Multitenancy\TenantAware;
use Luminor\Multitenancy\TenantContext;
use Luminor\Multitenancy\TenantInterface;
use Luminor\Multitenancy\TenantNotResolvedException;
use Luminor\Multitenancy\TenantScopedRepository;

final class TenantScopedRepositoryTest extends TestCase
{
    private InMemoryRepository $innerRepository;
    private TenantScopedRepository $scopedRepository;

    protected function setUp(): void
    {
        $this->innerRepository = new class extends InMemoryRepository {
            protected function getEntityClass(): string
            {
                return TenantAwareAggregate::class;
            }
        };

        $this->scopedRepository = new TenantScopedRepository($this->innerRepository);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    public function testFindByIdReturnsEntityFromCurrentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-1');
        $this->innerRepository->add($entity);

        $found = $this->scopedRepository->findById('entity-1');

        $this->assertSame($entity, $found);
    }

    public function testFindByIdReturnsNullForEntityFromDifferentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-2');
        $this->innerRepository->add($entity);

        $found = $this->scopedRepository->findById('entity-1');

        $this->assertNull($found);
    }

    public function testFindByIdThrowsWhenNoTenantContext(): void
    {
        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-1');
        $this->innerRepository->add($entity);

        $this->expectException(TenantNotResolvedException::class);
        $this->scopedRepository->findById('entity-1');
    }

    public function testFindAllReturnsOnlyEntitiesFromCurrentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity1 = new TenantAwareAggregate('entity-1');
        $entity1->setTenantId('tenant-1');

        $entity2 = new TenantAwareAggregate('entity-2');
        $entity2->setTenantId('tenant-2');

        $entity3 = new TenantAwareAggregate('entity-3');
        $entity3->setTenantId('tenant-1');

        $this->innerRepository->add($entity1);
        $this->innerRepository->add($entity2);
        $this->innerRepository->add($entity3);

        $found = $this->scopedRepository->findAll();

        $this->assertCount(2, $found);
        $this->assertContains($entity1, $found);
        $this->assertContains($entity3, $found);
        $this->assertNotContains($entity2, $found);
    }

    public function testAddAssignsTenantIdAutomatically(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $this->scopedRepository->add($entity);

        $this->assertSame('tenant-1', $entity->getTenantId());
    }

    public function testAddPreservesExistingTenantIdIfMatches(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-1');
        $this->scopedRepository->add($entity);

        $this->assertSame('tenant-1', $entity->getTenantId());
    }

    public function testAddThrowsIfTenantIdDoesNotMatch(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-2');

        $this->expectException(TenantAccessDeniedException::class);
        $this->scopedRepository->add($entity);
    }

    public function testUpdateSucceedsForEntityFromCurrentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-1');
        $this->innerRepository->add($entity);

        // Should not throw
        $this->scopedRepository->update($entity);
        $this->assertTrue(true);
    }

    public function testUpdateThrowsForEntityFromDifferentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-2');

        $this->expectException(TenantAccessDeniedException::class);
        $this->scopedRepository->update($entity);
    }

    public function testRemoveSucceedsForEntityFromCurrentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-1');
        $this->innerRepository->add($entity);

        $this->scopedRepository->remove($entity);

        $this->assertNull($this->innerRepository->findById('entity-1'));
    }

    public function testRemoveThrowsForEntityFromDifferentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-2');

        $this->expectException(TenantAccessDeniedException::class);
        $this->scopedRepository->remove($entity);
    }

    public function testRemoveByIdOnlyRemovesIfInCurrentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-2');
        $this->innerRepository->add($entity);

        // Should not remove entity from different tenant
        $this->scopedRepository->removeById('entity-1');

        // Entity should still exist in inner repository
        $this->assertNotNull($this->innerRepository->findById('entity-1'));
    }

    public function testCountReturnsOnlyEntitiesFromCurrentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity1 = new TenantAwareAggregate('entity-1');
        $entity1->setTenantId('tenant-1');

        $entity2 = new TenantAwareAggregate('entity-2');
        $entity2->setTenantId('tenant-2');

        $entity3 = new TenantAwareAggregate('entity-3');
        $entity3->setTenantId('tenant-1');

        $this->innerRepository->add($entity1);
        $this->innerRepository->add($entity2);
        $this->innerRepository->add($entity3);

        $this->assertSame(2, $this->scopedRepository->count());
    }

    public function testExistsReturnsTrueForEntityInCurrentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-1');
        $this->innerRepository->add($entity);

        $this->assertTrue($this->scopedRepository->exists('entity-1'));
    }

    public function testExistsReturnsFalseForEntityInDifferentTenant(): void
    {
        $this->setTenant('tenant-1');

        $entity = new TenantAwareAggregate('entity-1');
        $entity->setTenantId('tenant-2');
        $this->innerRepository->add($entity);

        $this->assertFalse($this->scopedRepository->exists('entity-1'));
    }

    public function testGetInnerRepositoryReturnsWrappedRepository(): void
    {
        $this->assertSame($this->innerRepository, $this->scopedRepository->getInnerRepository());
    }

    private function setTenant(string $tenantId): void
    {
        $tenant = new class($tenantId) implements TenantInterface {
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

        TenantContext::setTenant($tenant);
    }
}

/**
 * @extends AggregateRoot<string>
 */
final class TenantAwareAggregate extends AggregateRoot
{
    use TenantAware;

    public function __construct(string $id)
    {
        parent::__construct($id);
    }
}
