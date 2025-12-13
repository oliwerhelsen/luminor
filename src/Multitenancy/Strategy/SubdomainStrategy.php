<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy\Strategy;

use Luminor\DDD\Multitenancy\TenantResolverInterface;
use Utopia\Http\Request;

/**
 * Resolves tenant from the subdomain of the request host.
 *
 * For example, given a request to "acme.example.com", this strategy
 * would resolve the tenant identifier as "acme".
 */
final class SubdomainStrategy implements TenantResolverInterface
{
    /**
     * @param string $baseDomain The base domain to extract subdomain from (e.g., "example.com")
     * @param array<string> $excludedSubdomains Subdomains to exclude from tenant resolution (e.g., ["www", "api"])
     */
    public function __construct(
        private readonly string $baseDomain,
        private readonly array $excludedSubdomains = ['www', 'api', 'admin', 'mail']
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Request $request): ?string
    {
        $host = $request->getHostname();

        if (empty($host)) {
            return null;
        }

        // Check if the host ends with the base domain
        $baseDomainWithDot = '.' . ltrim($this->baseDomain, '.');

        if (!str_ends_with($host, $baseDomainWithDot) && $host !== $this->baseDomain) {
            return null;
        }

        // Extract the subdomain
        if ($host === $this->baseDomain) {
            return null;
        }

        $subdomain = substr($host, 0, -(strlen($baseDomainWithDot)));

        if (empty($subdomain)) {
            return null;
        }

        // Handle nested subdomains (e.g., "app.acme" -> "acme")
        $parts = explode('.', $subdomain);
        $tenantSubdomain = end($parts);

        // Check if subdomain is in the excluded list
        if (in_array(strtolower($tenantSubdomain), array_map('strtolower', $this->excludedSubdomains), true)) {
            return null;
        }

        return $tenantSubdomain;
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return 'subdomain';
    }
}
