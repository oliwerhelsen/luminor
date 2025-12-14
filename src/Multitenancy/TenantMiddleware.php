<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy;

use Luminor\DDD\Infrastructure\Http\Middleware\MiddlewareInterface;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

/**
 * Middleware that resolves and sets the current tenant.
 *
 * This middleware uses the configured tenant resolver to identify the tenant
 * from the incoming request, loads the tenant from the provider, and sets
 * it in the tenant context for use throughout the request lifecycle.
 */
final class TenantMiddleware implements MiddlewareInterface
{
    /**
     * @param TenantResolverInterface $resolver The resolver to identify tenant from request
     * @param TenantProviderInterface $provider The provider to load tenant data
     * @param bool $required Whether a tenant is required for all requests
     * @param array<string> $excludedPaths Paths that don't require tenant resolution (e.g., ["/health", "/api/public"])
     */
    public function __construct(
        private readonly TenantResolverInterface $resolver,
        private readonly TenantProviderInterface $provider,
        private readonly bool $required = true,
        private readonly array $excludedPaths = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        // Check if this path is excluded from tenant resolution
        if ($this->isExcludedPath($request)) {
            $next($request, $response);
            return;
        }

        // Resolve tenant identifier from request
        $tenantIdentifier = $this->resolver->resolve($request);

        if ($tenantIdentifier === null) {
            if ($this->required) {
                $this->sendTenantNotFoundResponse($response, 'Tenant identifier not provided.');
                return;
            }

            $next($request, $response);
            return;
        }

        // Load tenant from provider
        $tenant = $this->provider->findByIdentifier($tenantIdentifier);

        if ($tenant === null) {
            if ($this->required) {
                $this->sendTenantNotFoundResponse($response, sprintf('Tenant "%s" not found.', $tenantIdentifier));
                return;
            }

            $next($request, $response);
            return;
        }

        // Check if tenant is active
        if (!$tenant->isActive()) {
            $this->sendTenantInactiveResponse($response, $tenant);
            return;
        }

        // Set tenant in context
        TenantContext::setTenant($tenant);

        try {
            $next($request, $response);
        } finally {
            // Clear tenant context after request
            TenantContext::clear();
        }
    }

    /**
     * Check if the request path is in the excluded paths list.
     */
    private function isExcludedPath(Request $request): bool
    {
        $uri = $request->getURI();
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;

        foreach ($this->excludedPaths as $excludedPath) {
            // Exact match
            if ($path === $excludedPath) {
                return true;
            }

            // Wildcard match (e.g., "/api/public/*")
            if (str_ends_with($excludedPath, '*')) {
                $prefix = rtrim($excludedPath, '*');
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Send a tenant not found error response.
     */
    private function sendTenantNotFoundResponse(Response $response, string $message): void
    {
        $response
            ->setStatusCode(Response::STATUS_CODE_NOT_FOUND)
            ->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => $message,
                ],
            ]);
    }

    /**
     * Send a tenant inactive error response.
     */
    private function sendTenantInactiveResponse(Response $response, TenantInterface $tenant): void
    {
        $response
            ->setStatusCode(Response::STATUS_CODE_FORBIDDEN)
            ->json([
                'error' => [
                    'code' => 'TENANT_INACTIVE',
                    'message' => sprintf('Tenant "%s" is inactive.', $tenant->getSlug()),
                ],
            ]);
    }
}
