<?php

declare(strict_types=1);

namespace Lumina\DDD\Multitenancy;

use RuntimeException;

/**
 * Exception thrown when a tenant cannot be resolved or is required but not set.
 */
final class TenantNotResolvedException extends RuntimeException
{
    public function __construct(string $message = 'Tenant could not be resolved.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for a specific resolution strategy.
     */
    public static function forStrategy(string $strategy): self
    {
        return new self(sprintf('Tenant could not be resolved using %s strategy.', $strategy));
    }

    /**
     * Create exception when tenant is required but context is empty.
     */
    public static function contextEmpty(): self
    {
        return new self('No tenant is set in the current context. Ensure tenant resolution middleware is configured.');
    }

    /**
     * Create exception when tenant is inactive.
     */
    public static function tenantInactive(string|int $tenantId): self
    {
        return new self(sprintf('Tenant with ID "%s" is inactive and cannot be used.', $tenantId));
    }
}
