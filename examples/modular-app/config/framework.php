<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Modular E-Commerce Example',
        'env' => $_ENV['APP_ENV'] ?? 'development',
        'debug' => true,
    ],
    
    'modules' => [
        'autoload' => false,
        'enabled' => [
            \Example\ModularApp\Modules\Catalog\CatalogModule::class,
            \Example\ModularApp\Modules\Inventory\InventoryModule::class,
            \Example\ModularApp\Modules\Orders\OrdersModule::class,
        ],
    ],
];
