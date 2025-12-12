<?php

declare(strict_types=1);

namespace Lumina\DDD\Multitenancy;

use Utopia\Http\Request;

/**
 * Composite tenant resolver that tries multiple strategies in order.
 *
 * This resolver chains multiple resolution strategies and returns the first
 * successful resolution. It's useful when you want to support multiple
 * ways of identifying tenants (e.g., subdomain first, then header fallback).
 */
final class TenantResolver implements TenantResolverInterface
{
    /** @var array<TenantResolverInterface> */
    private array $resolvers = [];

    /**
     * @param array<TenantResolverInterface> $resolvers The resolvers to use, in priority order
     */
    public function __construct(array $resolvers = [])
    {
        foreach ($resolvers as $resolver) {
            $this->addResolver($resolver);
        }
    }

    /**
     * Add a resolver to the chain.
     *
     * @return $this
     */
    public function addResolver(TenantResolverInterface $resolver): self
    {
        $this->resolvers[] = $resolver;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Request $request): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $tenantIdentifier = $resolver->resolve($request);

            if ($tenantIdentifier !== null) {
                return $tenantIdentifier;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return 'composite';
    }

    /**
     * Get the list of configured resolvers.
     *
     * @return array<TenantResolverInterface>
     */
    public function getResolvers(): array
    {
        return $this->resolvers;
    }
}
