# Multitenancy

The framework provides built-in support for multi-tenant applications with different tenant resolution strategies.

## Configuration

Enable multitenancy in your configuration:

```php
<?php

// config/framework.php
return [
    'multitenancy' => [
        'enabled' => true,
        'strategy' => 'header', // header, subdomain, or path
        'header_name' => 'X-Tenant-ID',
        'default_tenant' => null,
    ],
];
```

## Tenant Resolution Strategies

### Header Strategy

Resolve tenant from a request header:

```php
<?php

use Luminor\DDD\Multitenancy\Strategy\HeaderStrategy;

$strategy = new HeaderStrategy('X-Tenant-ID');

// Request with header "X-Tenant-ID: tenant-123"
$tenantId = $strategy->resolve($request); // Returns "tenant-123"
```

### Subdomain Strategy

Resolve tenant from subdomain:

```php
<?php

use Luminor\DDD\Multitenancy\Strategy\SubdomainStrategy;

$strategy = new SubdomainStrategy();

// Request to "acme.example.com"
$tenantId = $strategy->resolve($request); // Returns "acme"
```

### Path Strategy

Resolve tenant from URL path:

```php
<?php

use Luminor\DDD\Multitenancy\Strategy\PathStrategy;

$strategy = new PathStrategy();

// Request to "/tenants/acme/products"
$tenantId = $strategy->resolve($request); // Returns "acme"
```

## Tenant Context

Access the current tenant throughout your application:

```php
<?php

use Luminor\DDD\Multitenancy\TenantContext;

// In a controller or service
public function __construct(
    private readonly TenantContext $tenantContext,
) {
}

public function someMethod(): void
{
    // Get the current tenant
    $tenant = $this->tenantContext->getTenant();
    
    // Check if in a tenant context
    if ($this->tenantContext->hasTenant()) {
        $tenantId = $tenant->getId();
    }
}
```

## Tenant Middleware

The tenant middleware resolves and sets the current tenant:

```php
<?php

use Luminor\DDD\Multitenancy\TenantMiddleware;
use Luminor\DDD\Multitenancy\TenantResolver;

$resolver = new TenantResolver(
    strategy: new HeaderStrategy('X-Tenant-ID'),
    tenantProvider: $tenantRepository,
);

$middleware = new TenantMiddleware($resolver, $tenantContext);

// Apply to routes
$http->get('/products')
    ->middleware($middleware)
    ->action(function () {
        // Tenant is now available via TenantContext
    });
```

## Tenant-Aware Entities

Use the `TenantAware` trait for entities that belong to a tenant:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use Luminor\DDD\Domain\Abstractions\Entity;
use Luminor\DDD\Multitenancy\TenantAware;

final class Product extends Entity
{
    use TenantAware;

    public function __construct(
        string $id,
        string $tenantId,
        private string $name,
    ) {
        parent::__construct($id);
        $this->setTenantId($tenantId);
    }
}
```

## Tenant-Scoped Repository

The `TenantScopedRepository` automatically filters queries by tenant:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Luminor\DDD\Multitenancy\TenantScopedRepository;

final class ProductRepository extends TenantScopedRepository implements ProductRepositoryInterface
{
    public function findByCategory(string $category): array
    {
        // Automatically scoped to current tenant
        return $this->findBy(['category' => $category]);
    }
}
```

## Custom Tenant Implementation

Implement the `TenantInterface` for your tenant entity:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use Luminor\DDD\Domain\Abstractions\Entity;
use Luminor\DDD\Multitenancy\TenantInterface;

final class Organization extends Entity implements TenantInterface
{
    public function __construct(
        string $id,
        private string $name,
        private string $slug,
        private TenantSettings $settings,
    ) {
        parent::__construct($id);
    }

    public function getTenantId(): string
    {
        return $this->getId();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getSettings(): TenantSettings
    {
        return $this->settings;
    }
}
```

## Tenant Provider

Implement a tenant provider to load tenants:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Multitenancy;

use Luminor\DDD\Multitenancy\TenantProviderInterface;
use Luminor\DDD\Multitenancy\TenantInterface;

final class DatabaseTenantProvider implements TenantProviderInterface
{
    public function __construct(
        private readonly OrganizationRepository $repository,
    ) {
    }

    public function findById(string $id): ?TenantInterface
    {
        return $this->repository->findById($id);
    }

    public function findByIdentifier(string $identifier): ?TenantInterface
    {
        return $this->repository->findBySlug($identifier);
    }
}
```

## Database Per Tenant

For complete data isolation, use database-per-tenant:

```php
<?php

final class TenantDatabaseManager
{
    private array $connections = [];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly array $config,
    ) {
    }

    public function getConnection(): Connection
    {
        $tenant = $this->tenantContext->getTenant();
        $tenantId = $tenant->getTenantId();

        if (!isset($this->connections[$tenantId])) {
            $this->connections[$tenantId] = $this->createConnection($tenant);
        }

        return $this->connections[$tenantId];
    }

    private function createConnection(TenantInterface $tenant): Connection
    {
        $config = $this->config;
        $config['database'] = 'tenant_' . $tenant->getTenantId();
        
        return new Connection($config);
    }
}
```

## Handling Tenant Exceptions

```php
<?php

use Luminor\DDD\Multitenancy\TenantNotResolvedException;
use Luminor\DDD\Multitenancy\TenantAccessDeniedException;

// In exception handler
$handler->register(TenantNotResolvedException::class, function ($e, $response) {
    return $response
        ->setStatusCode(400)
        ->json(['error' => 'Tenant not specified']);
});

$handler->register(TenantAccessDeniedException::class, function ($e, $response) {
    return $response
        ->setStatusCode(403)
        ->json(['error' => 'Access to this tenant is denied']);
});
```

## Best Practices

1. **Choose the right strategy**: Use subdomain for SaaS, header for APIs
2. **Always scope queries**: Use `TenantScopedRepository` to prevent data leaks
3. **Validate tenant access**: Verify users have access to the tenant
4. **Consider data isolation**: Choose between shared DB or DB-per-tenant
5. **Handle missing tenants**: Always check for tenant context before querying
6. **Cache tenant data**: Tenant resolution happens on every request
