# Modules

The module system allows you to organize your application into self-contained, reusable units. Each module encapsulates its own domain, application, and infrastructure layers.

## Creating a Module

Use the CLI to scaffold a new module:

```bash
./vendor/bin/lumina make:module Inventory
```

This creates the following structure:

```
src/Modules/Inventory/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Events/
│   ├── Repository/
│   └── Exceptions/
├── Application/
│   ├── Commands/
│   ├── Queries/
│   ├── Handlers/
│   ├── DTOs/
│   └── Services/
├── Infrastructure/
│   ├── Persistence/
│   └── Http/
│       └── Controllers/
├── InventoryModule.php
├── InventoryServiceProvider.php
└── routes.php
```

## Module Class

The module class defines the module's metadata and boot sequence:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use Lumina\DDD\Module\AbstractModule;
use Lumina\DDD\Module\ModuleDefinition;

final class InventoryModule extends AbstractModule
{
    public function getDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            name: 'Inventory',
            version: '1.0.0',
            description: 'Inventory management module',
            dependencies: ['Products'], // Other modules this depends on
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
        // Load routes
        $this->loadRoutes(__DIR__ . '/routes.php');

        // Register event listeners
        $this->registerEventListeners();
    }

    private function registerEventListeners(): void
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);

        $dispatcher->subscribe(
            OrderPlacedEvent::class,
            $this->container->get(ReserveInventoryHandler::class)
        );
    }
}
```

## Service Provider

The service provider registers module dependencies:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use Lumina\DDD\Container\AbstractServiceProvider;
use Lumina\DDD\Container\ContainerInterface;

final class InventoryServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register repositories
        $container->bind(
            InventoryRepositoryInterface::class,
            DoctrineInventoryRepository::class
        );

        // Register services
        $container->singleton(InventoryService::class, function ($c) {
            return new InventoryService(
                $c->get(InventoryRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class),
            );
        });

        // Register command handlers
        $this->registerCommandHandlers($container);

        // Register query handlers
        $this->registerQueryHandlers($container);
    }

    private function registerCommandHandlers(ContainerInterface $container): void
    {
        $commandBus = $container->get(CommandBusInterface::class);

        $commandBus->registerHandler(
            AdjustStockCommand::class,
            $container->get(AdjustStockCommandHandler::class)
        );
    }

    private function registerQueryHandlers(ContainerInterface $container): void
    {
        $queryBus = $container->get(QueryBusInterface::class);

        $queryBus->registerHandler(
            GetStockLevelQuery::class,
            $container->get(GetStockLevelQueryHandler::class)
        );
    }

    public function provides(): array
    {
        return [
            InventoryRepositoryInterface::class,
            InventoryService::class,
        ];
    }
}
```

## Module Routes

Define routes in the module's routes file:

```php
<?php

// src/Modules/Inventory/routes.php

declare(strict_types=1);

use Utopia\Http\Http;

$http = Http::getInstance();

// Stock management
$http->get('/inventory/:productId')
    ->param('productId', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->inject('inventoryController')
    ->action(function ($productId, $request, $response, $controller) {
        return $controller->getStock($request, $response, $productId);
    });

$http->post('/inventory/:productId/adjust')
    ->param('productId', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->inject('inventoryController')
    ->action(function ($productId, $request, $response, $controller) {
        return $controller->adjustStock($request, $response, $productId);
    });
```

## Loading Modules

Configure module loading in your kernel:

```php
<?php

// config/framework.php
return [
    'modules' => [
        'autoload' => true,
        'path' => __DIR__ . '/../src/Modules',

        // Or explicitly list modules
        'enabled' => [
            \App\Modules\Inventory\InventoryModule::class,
            \App\Modules\Orders\OrdersModule::class,
            \App\Modules\Shipping\ShippingModule::class,
        ],
    ],
];
```

## Module Communication

### Using Events

Modules communicate through domain events:

```php
<?php

// In Orders module - publishing event
final class OrderPlacedEvent extends DomainEvent
{
    public function __construct(
        string $orderId,
        public readonly array $items,
    ) {
        parent::__construct($orderId);
    }
}

// In Inventory module - handling event
final class ReserveInventoryHandler implements EventHandlerInterface
{
    public function __invoke(OrderPlacedEvent $event): void
    {
        foreach ($event->items as $item) {
            $this->inventoryService->reserve(
                $item['productId'],
                $item['quantity']
            );
        }
    }
}
```

### Using Module Services

For synchronous communication, inject services:

```php
<?php

// Orders module using Inventory service
final class PlaceOrderCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InventoryServiceInterface $inventoryService,
    ) {
    }

    public function __invoke(PlaceOrderCommand $command): string
    {
        // Check inventory before placing order
        foreach ($command->items as $item) {
            if (!$this->inventoryService->isAvailable($item['productId'], $item['quantity'])) {
                throw new InsufficientInventoryException($item['productId']);
            }
        }

        // ... continue with order placement
    }
}
```

## Module Testing

Test modules in isolation:

```php
<?php

declare(strict_types=1);

namespace Tests\Modules\Inventory;

use Lumina\DDD\Testing\TestCase;
use App\Modules\Inventory\InventoryModule;

final class InventoryModuleTest extends TestCase
{
    private InventoryModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new InventoryModule($this->container);
        $this->module->boot();
    }

    public function testCanAdjustStock(): void
    {
        $command = new AdjustStockCommand(
            productId: 'product-1',
            quantity: 10,
            reason: 'Initial stock'
        );

        $this->getCommandBus()->dispatch($command);

        $stock = $this->getQueryBus()->dispatch(
            new GetStockLevelQuery('product-1')
        );

        $this->assertSame(10, $stock->quantity);
    }
}
```

## Best Practices

1. **Keep modules cohesive**: Each module should have a single, clear purpose
2. **Minimize dependencies**: Modules should be loosely coupled
3. **Use events for communication**: Prefer async communication between modules
4. **Define clear interfaces**: Use interfaces for cross-module dependencies
5. **Test in isolation**: Each module should be testable independently
6. **Version your modules**: Track module versions for compatibility
