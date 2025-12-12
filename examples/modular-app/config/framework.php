<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => env('APP_NAME', 'Modular E-Commerce Example'),
        'env' => env('APP_ENV', 'development'),
        'debug' => (bool) env('APP_DEBUG', true),
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
