<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy;

use Utopia\Http\Request;

/**
 * Interface for tenant resolution strategies.
 *
 * Implementations determine how to identify the tenant from an incoming request.
 */
interface TenantResolverInterface
{
    /**
     * Resolve the tenant identifier from the request.
     *
     * @param Request $request The incoming HTTP request
     *
     * @return string|null The tenant identifier or null if not resolvable
     */
    public function resolve(Request $request): ?string;

    /**
     * Get the name of this resolution strategy.
     */
    public function getStrategyName(): string;
}
