<?php

declare(strict_types=1);

namespace Luminor\Multitenancy;

/**
 * Trait for entities that belong to a specific tenant.
 *
 * Use this trait in domain entities that should be scoped to a tenant.
 * The trait provides methods for getting and setting the tenant ID,
 * which is used by tenant-scoped repositories to filter data.
 */
trait TenantAware
{
    protected string|int|null $tenantId = null;

    /**
     * Get the tenant ID this entity belongs to.
     *
     * @return string|int|null The tenant ID or null if not set
     */
    public function getTenantId(): string|int|null
    {
        return $this->tenantId;
    }

    /**
     * Set the tenant ID this entity belongs to.
     *
     * @param string|int|null $tenantId The tenant ID
     * @return $this
     */
    public function setTenantId(string|int|null $tenantId): static
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Check if this entity has a tenant assigned.
     */
    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Check if this entity belongs to the given tenant.
     *
     * @param string|int $tenantId The tenant ID to check against
     */
    public function belongsToTenant(string|int $tenantId): bool
    {
        return $this->tenantId === $tenantId;
    }

    /**
     * Assign this entity to the current tenant from context.
     *
     * @throws TenantNotResolvedException If no tenant is in context
     * @return $this
     */
    public function assignToCurrentTenant(): static
    {
        $tenant = TenantContext::getTenantOrFail();
        $this->tenantId = $tenant->getId();
        return $this;
    }
}
