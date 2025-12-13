<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy;

/**
 * Interface representing a tenant in a multi-tenant application.
 *
 * A tenant is typically an organization, company, or isolated customer
 * that has their own set of data and configurations within the application.
 */
interface TenantInterface
{
    /**
     * Get the tenant's unique identifier.
     *
     * @return string|int The unique identifier for this tenant
     */
    public function getId(): string|int;

    /**
     * Get the tenant's unique slug or code.
     *
     * This is typically used for subdomain resolution or URL paths.
     */
    public function getSlug(): string;

    /**
     * Get the tenant's display name.
     */
    public function getName(): string;

    /**
     * Check if the tenant is currently active.
     *
     * Inactive tenants should not be able to access the application.
     */
    public function isActive(): bool;

    /**
     * Get tenant-specific configuration value.
     *
     * @param string $key The configuration key
     * @param mixed $default Default value if key doesn't exist
     *
     * @return mixed The configuration value
     */
    public function getConfig(string $key, mixed $default = null): mixed;

    /**
     * Get all tenant configuration values.
     *
     * @return array<string, mixed>
     */
    public function getAllConfig(): array;
}
