<?php

declare(strict_types=1);

namespace Lumina\DDD\Multitenancy\Strategy;

use Lumina\DDD\Multitenancy\TenantResolverInterface;
use Utopia\Http\Request;

/**
 * Resolves tenant from the URL path.
 *
 * This strategy extracts the tenant identifier from a specific segment
 * of the URL path. For example, with position 0, "/acme/api/users"
 * would resolve the tenant identifier as "acme".
 */
final class PathStrategy implements TenantResolverInterface
{
    /**
     * @param int $position The position in the path segments (0-indexed) where the tenant identifier is expected
     * @param string $prefix Optional prefix that must precede the tenant segment (e.g., "tenants" for "/tenants/acme/...")
     */
    public function __construct(
        private readonly int $position = 0,
        private readonly ?string $prefix = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Request $request): ?string
    {
        $path = $request->getURI();

        if (empty($path)) {
            return null;
        }

        // Parse the path and extract segments
        $path = parse_url($path, PHP_URL_PATH);

        if ($path === null || $path === false) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));

        if (empty($segments)) {
            return null;
        }

        // If prefix is specified, find it and adjust position
        if ($this->prefix !== null) {
            $prefixPosition = array_search($this->prefix, $segments, true);

            if ($prefixPosition === false) {
                return null;
            }

            $tenantPosition = $prefixPosition + 1;
        } else {
            $tenantPosition = $this->position;
        }

        if (!isset($segments[$tenantPosition])) {
            return null;
        }

        $tenantIdentifier = $segments[$tenantPosition];

        // Validate tenant identifier (alphanumeric and hyphens only)
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$|^[a-zA-Z0-9]$/', $tenantIdentifier)) {
            return null;
        }

        return $tenantIdentifier;
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return 'path';
    }

    /**
     * Get the position in the path where tenant identifier is expected.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Get the prefix that must precede the tenant segment.
     */
    public function getPrefix(): ?string
    {
        return $this->prefix;
    }
}
