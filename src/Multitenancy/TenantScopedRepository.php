<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy;

use Luminor\DDD\Domain\Abstractions\AggregateRoot;
use Luminor\DDD\Domain\Repository\AggregateNotFoundException;
use Luminor\DDD\Domain\Repository\Criteria;
use Luminor\DDD\Domain\Repository\Filter\EqualsFilter;
use Luminor\DDD\Domain\Repository\RepositoryInterface;
use ReflectionClass;

/**
 * Repository decorator that automatically scopes all operations to the current tenant.
 *
 * This decorator wraps any repository and ensures that:
 * - All queries are filtered by the current tenant ID
 * - All new entities are assigned to the current tenant before saving
 * - Access to entities from other tenants is prevented
 *
 * @template T of AggregateRoot
 *
 * @implements RepositoryInterface<T>
 */
final class TenantScopedRepository implements RepositoryInterface
{
    /**
     * @param RepositoryInterface<T> $innerRepository The repository to decorate
     * @param string $tenantIdField The name of the tenant ID field on entities
     */
    public function __construct(
        private readonly RepositoryInterface $innerRepository,
        private readonly string $tenantIdField = 'tenantId',
    ) {
    }

    /**
     * @inheritDoc
     */
    public function findById(mixed $id): ?AggregateRoot
    {
        $entity = $this->innerRepository->findById($id);

        if ($entity === null) {
            return null;
        }

        // Verify entity belongs to current tenant
        if (! $this->belongsToCurrentTenant($entity)) {
            return null;
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function findByIdOrFail(mixed $id): AggregateRoot
    {
        $entity = $this->findById($id);

        if ($entity === null) {
            throw new AggregateNotFoundException(
                sprintf('Aggregate with ID "%s" not found in current tenant context.', $id),
            );
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        return $this->findByCriteria(Criteria::create());
    }

    /**
     * @inheritDoc
     */
    public function findByCriteria(Criteria $criteria): array
    {
        $tenantCriteria = $this->applyTenantFilter($criteria);

        return $this->innerRepository->findByCriteria($tenantCriteria);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->countByCriteria(Criteria::create());
    }

    /**
     * @inheritDoc
     */
    public function countByCriteria(Criteria $criteria): int
    {
        $tenantCriteria = $this->applyTenantFilter($criteria);

        return $this->innerRepository->countByCriteria($tenantCriteria);
    }

    /**
     * @inheritDoc
     */
    public function exists(mixed $id): bool
    {
        return $this->findById($id) !== null;
    }

    /**
     * @inheritDoc
     */
    public function add(AggregateRoot $aggregate): void
    {
        $this->assignTenantIfNeeded($aggregate);
        $this->innerRepository->add($aggregate);
    }

    /**
     * @inheritDoc
     */
    public function update(AggregateRoot $aggregate): void
    {
        // Verify entity belongs to current tenant
        if (! $this->belongsToCurrentTenant($aggregate)) {
            throw new TenantAccessDeniedException(
                'Cannot update an entity that belongs to a different tenant.',
            );
        }

        $this->innerRepository->update($aggregate);
    }

    /**
     * @inheritDoc
     */
    public function remove(AggregateRoot $aggregate): void
    {
        // Verify entity belongs to current tenant
        if (! $this->belongsToCurrentTenant($aggregate)) {
            throw new TenantAccessDeniedException(
                'Cannot remove an entity that belongs to a different tenant.',
            );
        }

        $this->innerRepository->remove($aggregate);
    }

    /**
     * @inheritDoc
     */
    public function removeById(mixed $id): void
    {
        // First verify the entity exists and belongs to current tenant
        $entity = $this->findById($id);

        if ($entity === null) {
            return;
        }

        $this->innerRepository->removeById($id);
    }

    /**
     * Get the inner repository.
     *
     * @return RepositoryInterface<T>
     */
    public function getInnerRepository(): RepositoryInterface
    {
        return $this->innerRepository;
    }

    /**
     * Apply tenant filter to criteria.
     */
    private function applyTenantFilter(Criteria $criteria): Criteria
    {
        $tenantId = TenantContext::getTenantId();

        if ($tenantId === null) {
            throw TenantNotResolvedException::contextEmpty();
        }

        $tenantFilter = new EqualsFilter($this->tenantIdField, $tenantId);

        return $criteria->filter($tenantFilter);
    }

    /**
     * Check if an entity belongs to the current tenant.
     */
    private function belongsToCurrentTenant(AggregateRoot $entity): bool
    {
        $tenantId = TenantContext::getTenantId();

        if ($tenantId === null) {
            throw TenantNotResolvedException::contextEmpty();
        }

        // Check if entity uses TenantAware trait
        if (method_exists($entity, 'getTenantId')) {
            return $entity->getTenantId() === $tenantId;
        }

        // Try reflection for tenantId property
        $reflection = new ReflectionClass($entity);

        if ($reflection->hasProperty($this->tenantIdField)) {
            $property = $reflection->getProperty($this->tenantIdField);

            return $property->getValue($entity) === $tenantId;
        }

        // If entity doesn't have tenant ID, assume it doesn't belong to any tenant
        return false;
    }

    /**
     * Assign tenant ID to entity if it uses TenantAware trait.
     */
    private function assignTenantIfNeeded(AggregateRoot $aggregate): void
    {
        if (! method_exists($aggregate, 'setTenantId')) {
            return;
        }

        // Only assign if not already set
        if (method_exists($aggregate, 'getTenantId') && $aggregate->getTenantId() !== null) {
            // Verify it matches current tenant
            $tenantId = TenantContext::getTenantId();

            if ($tenantId !== null && $aggregate->getTenantId() !== $tenantId) {
                throw new TenantAccessDeniedException(
                    'Cannot add an entity that belongs to a different tenant.',
                );
            }

            return;
        }

        $tenantId = TenantContext::getTenantId();

        if ($tenantId === null) {
            throw TenantNotResolvedException::contextEmpty();
        }

        $aggregate->setTenantId($tenantId);
    }
}
