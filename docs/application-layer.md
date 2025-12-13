---
title: Application Layer
layout: default
parent: Core Concepts
nav_order: 2
description: "CQRS, Commands, Queries, Handlers, DTOs, and Validation"
---

# Application Layer

The application layer orchestrates the domain layer to perform use cases. It contains commands, queries, handlers, and application services.

## CQRS Pattern

The framework implements Command Query Responsibility Segregation (CQRS) to separate read and write operations.

### Commands

Commands represent intentions to change the system state.

```php
<?php

declare(strict_types=1);

namespace App\Application\Commands;

use Luminor\DDD\Application\CQRS\Command;

final class PlaceOrderCommand implements Command
{
    public function __construct(
        public readonly string $customerId,
        /** @var array<array{productId: string, quantity: int}> */
        public readonly array $items,
        public readonly ?string $shippingAddressId = null,
    ) {
    }
}
```

### Command Handlers

Command handlers execute the business logic for commands.

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Commands\PlaceOrderCommand;
use App\Domain\Entities\Order;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use Luminor\DDD\Application\Bus\CommandHandlerInterface;

final class PlaceOrderCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(PlaceOrderCommand $command): string
    {
        // Create the order aggregate
        $order = Order::place(new CustomerId($command->customerId));

        // Add line items
        foreach ($command->items as $item) {
            $product = $this->productRepository->findById($item['productId']);

            if ($product === null) {
                throw new ProductNotFoundException($item['productId']);
            }

            $order->addLine($product, $item['quantity']);
        }

        // Submit the order
        $order->submit();

        // Persist
        $this->orderRepository->save($order);

        // Dispatch domain events
        foreach ($order->pullEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $order->getId();
    }
}
```

### Queries

Queries retrieve data without modifying state.

```php
<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Luminor\DDD\Application\CQRS\Query;

final class GetOrderDetailsQuery implements Query
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
```

### Query Handlers

Query handlers retrieve and return data.

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\DTOs\OrderDetailsDto;
use App\Application\Queries\GetOrderDetailsQuery;
use App\Domain\Repository\OrderRepositoryInterface;
use Luminor\DDD\Application\Bus\QueryHandlerInterface;

final class GetOrderDetailsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(GetOrderDetailsQuery $query): ?OrderDetailsDto
    {
        $order = $this->orderRepository->findById($query->orderId);

        if ($order === null) {
            return null;
        }

        return OrderDetailsDto::fromEntity($order);
    }
}
```

## Command Bus

The command bus dispatches commands to their handlers.

```php
<?php

use Luminor\DDD\Application\Bus\CommandBusInterface;

// In your controller or service
public function placeOrder(Request $request): Response
{
    $command = new PlaceOrderCommand(
        customerId: $request->getPayload()['customerId'],
        items: $request->getPayload()['items'],
    );

    $orderId = $this->commandBus->dispatch($command);

    return $this->created($response, ['orderId' => $orderId]);
}
```

## Query Bus

The query bus dispatches queries to their handlers.

```php
<?php

use Luminor\DDD\Application\Bus\QueryBusInterface;

public function getOrder(string $orderId): Response
{
    $query = new GetOrderDetailsQuery($orderId);
    $order = $this->queryBus->dispatch($query);

    if ($order === null) {
        return $this->notFound($response);
    }

    return $this->success($response, ['data' => $order]);
}
```

## Data Transfer Objects (DTOs)

DTOs carry data between layers without exposing domain objects.

```php
<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use Luminor\DDD\Application\DTO\DataTransferObject;
use App\Domain\Entities\Order;

final class OrderDetailsDto extends DataTransferObject
{
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $status,
        /** @var OrderLineDto[] */
        public readonly array $lines,
        public readonly int $totalCents,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId(),
            customerId: $order->getCustomerId()->toString(),
            status: $order->getStatus()->value,
            lines: array_map(
                fn($line) => OrderLineDto::fromEntity($line),
                $order->getLines()
            ),
            totalCents: $order->getTotal()->getAmount(),
            createdAt: $order->getCreatedAt()->format('c'),
        );
    }
}
```

## Validation

Use the command validator for input validation.

```php
<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Application\Commands\PlaceOrderCommand;
use Luminor\DDD\Application\Validation\CommandValidator;
use Luminor\DDD\Application\Validation\Rules;

final class PlaceOrderValidator extends CommandValidator
{
    public function rules(): array
    {
        return [
            'customerId' => [Rules::required(), Rules::uuid()],
            'items' => [Rules::required(), Rules::array(), Rules::minCount(1)],
            'items.*.productId' => [Rules::required(), Rules::uuid()],
            'items.*.quantity' => [Rules::required(), Rules::integer(), Rules::min(1)],
        ];
    }
}
```

## Application Services

For complex operations that don't fit the command/query pattern:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use Luminor\DDD\Application\Services\ApplicationService;

final class OrderFulfillmentService extends ApplicationService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InventoryService $inventoryService,
        private readonly ShippingService $shippingService,
    ) {
    }

    public function fulfill(string $orderId): FulfillmentResult
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order === null) {
            return FulfillmentResult::failed('Order not found');
        }

        // Reserve inventory
        $reservation = $this->inventoryService->reserve($order);

        if (!$reservation->isSuccessful()) {
            return FulfillmentResult::failed('Inventory unavailable');
        }

        // Create shipment
        $shipment = $this->shippingService->createShipment($order);

        // Update order status
        $order->markAsFulfilled($shipment);
        $this->orderRepository->save($order);

        return FulfillmentResult::success($shipment);
    }
}
```

## Best Practices

1. **Keep handlers focused**: One handler per command/query
2. **Use DTOs**: Never expose domain entities directly to the API
3. **Validate early**: Validate commands before processing
4. **Handle errors gracefully**: Use domain exceptions and translate them
5. **Keep commands/queries immutable**: Use readonly properties
