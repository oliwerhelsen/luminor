<?php

declare(strict_types=1);

namespace Lumina\DDD\Multitenancy\Strategy;

use Lumina\DDD\Multitenancy\TenantResolverInterface;
use Utopia\Http\Request;

/**
 * Resolves tenant from an HTTP header.
 *
 * This strategy is useful for APIs where clients explicitly specify
 * the tenant they want to interact with via a custom header.
 */
final class HeaderStrategy implements TenantResolverInterface
{
    /**
     * @param string $headerName The name of the header containing the tenant identifier
     */
    public function __construct(
        private readonly string $headerName = 'X-Tenant-ID'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Request $request): ?string
    {
        $value = $request->getHeader($this->headerName);

        if (empty($value)) {
            return null;
        }

        return trim($value);
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return 'header';
    }

    /**
     * Get the header name used for tenant resolution.
     */
    public function getHeaderName(): string
    {
        return $this->headerName;
    }
}
