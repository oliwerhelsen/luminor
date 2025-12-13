<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Catalog;

use Luminor\DDD\Container\ContainerInterface;
use Luminor\DDD\Module\AbstractModule;
use Luminor\DDD\Module\ModuleDefinition;

/**
 * Catalog Module - Product catalog management.
 */
final class CatalogModule extends AbstractModule
{
    public function getDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            name: 'Catalog',
            version: '1.0.0',
            description: 'Product catalog management module',
            dependencies: [],
        );
    }

    public function getServiceProviders(): array
    {
        return [
            CatalogServiceProvider::class,
        ];
    }

    public function boot(): void
    {
        // Module-specific bootstrapping
    }
}
