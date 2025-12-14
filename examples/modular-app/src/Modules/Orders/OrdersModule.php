<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders;

use Luminor\Module\AbstractModule;
use Luminor\Module\ModuleDefinition;

/**
 * Orders Module - Order management.
 */
final class OrdersModule extends AbstractModule
{
    public function getDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            name: 'Orders',
            version: '1.0.0',
            description: 'Order management module',
            dependencies: ['Catalog', 'Inventory'],
        );
    }

    public function getServiceProviders(): array
    {
        return [
            OrdersServiceProvider::class,
        ];
    }

    public function boot(): void
    {
        // Module-specific bootstrapping
    }
}
