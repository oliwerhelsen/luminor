<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy;

/**
 * Interface for tenant providers.
 *
 * A tenant provider is responsible for loading tenant information
 * from a data source (database, cache, configuration, etc.).
 */
interface TenantProviderInterface
{
    /**
     * Find a tenant by its identifier (ID or slug).
     *
     * @param string $identifier The tenant ID or slug
     * @return TenantInterface|null The tenant or null if not found
     */
    public function findByIdentifier(string $identifier): ?TenantInterface;

    /**
     * Find a tenant by its ID.
     *
     * @param string|int $id The tenant ID
     * @return TenantInterface|null The tenant or null if not found
     */
    public function findById(string|int $id): ?TenantInterface;

    /**
     * Find a tenant by its slug.
     *
     * @param string $slug The tenant slug
     * @return TenantInterface|null The tenant or null if not found
     */
    public function findBySlug(string $slug): ?TenantInterface;

    /**
     * Get all active tenants.
     *
     * @return array<TenantInterface>
     */
    public function findAllActive(): array;
}
