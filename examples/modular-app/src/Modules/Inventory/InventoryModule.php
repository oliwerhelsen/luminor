<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Inventory;

use Luminor\Module\AbstractModule;
use Luminor\Module\ModuleDefinition;

/**
 * Inventory Module - Stock management.
 */
final class InventoryModule extends AbstractModule
{
    public function getDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            name: 'Inventory',
            version: '1.0.0',
            description: 'Inventory and stock management module',
            dependencies: ['Catalog'],
        );
    }

    public function getServiceProviders(): array
    {
        return [
            InventoryServiceProvider::class,
        ];
    }

    public function boot(): void
    {
        // Register event listeners for cross-module communication
    }
}
