# Testing

The framework provides testing utilities to help you write effective unit and integration tests.

## Test Case Base Class

Extend the `TestCase` class for access to testing utilities:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use Lumina\DDD\Testing\TestCase;

final class CreateOrderCommandHandlerTest extends TestCase
{
    private CreateOrderCommandHandler $handler;
    private InMemoryOrderRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->orderRepository = new InMemoryOrderRepository();
        $this->handler = new CreateOrderCommandHandler(
            $this->orderRepository,
            $this->getEventDispatcher(),
        );
    }

    public function testCreatesOrder(): void
    {
        $command = new CreateOrderCommand(
            customerId: 'customer-1',
            items: [
                ['productId' => 'product-1', 'quantity' => 2],
            ],
        );

        $orderId = ($this->handler)($command);

        $this->assertNotEmpty($orderId);
        $this->assertNotNull($this->orderRepository->findById($orderId));
    }

    public function testDispatchesOrderCreatedEvent(): void
    {
        $command = new CreateOrderCommand(
            customerId: 'customer-1',
            items: [
                ['productId' => 'product-1', 'quantity' => 2],
            ],
        );

        ($this->handler)($command);

        $this->assertEventDispatched(OrderCreatedEvent::class);
    }
}
```

## In-Memory Buses

Use in-memory implementations for isolated testing:

### InMemoryCommandBus

```php
<?php

use Lumina\DDD\Testing\InMemoryCommandBus;

$commandBus = new InMemoryCommandBus();

// Set up handlers
$commandBus->handle(CreateUserCommand::class, function ($command) {
    return 'user-123';
});

// Dispatch command
$result = $commandBus->dispatch(new CreateUserCommand('John', 'john@example.com'));

// Assert commands were dispatched
$this->assertTrue($commandBus->hasDispatched(CreateUserCommand::class));
$this->assertSame(1, $commandBus->getDispatchCount(CreateUserCommand::class));

// Get the last command
$lastCommand = $commandBus->getLastCommand();

// Assert nothing was dispatched
$commandBus->reset();
$commandBus->assertNothingDispatched();
```

### InMemoryQueryBus

```php
<?php

use Lumina\DDD\Testing\InMemoryQueryBus;

$queryBus = new InMemoryQueryBus();

// Set up predefined results
$queryBus->willReturn(GetUserQuery::class, new UserDto(
    id: 'user-123',
    name: 'John Doe',
    email: 'john@example.com',
));

// Or use a handler
$queryBus->handle(GetUserQuery::class, function ($query) {
    return new UserDto(...);
});

// Dispatch query
$result = $queryBus->dispatch(new GetUserQuery('user-123'));

// Assert queries were dispatched
$this->assertTrue($queryBus->hasDispatched(GetUserQuery::class));
```

### InMemoryEventDispatcher

```php
<?php

use Lumina\DDD\Testing\InMemoryEventDispatcher;

$dispatcher = new InMemoryEventDispatcher();

// Set up listeners
$dispatcher->listen(OrderCreatedEvent::class, function ($event) {
    // Handle event
});

// Dispatch event
$dispatcher->dispatch(new OrderCreatedEvent('order-123'));

// Assertions
$this->assertTrue($dispatcher->hasDispatched(OrderCreatedEvent::class));
$this->assertSame(1, $dispatcher->getDispatchCount(OrderCreatedEvent::class));

// Assert with custom condition
$dispatcher->assertDispatchedWith(
    OrderCreatedEvent::class,
    fn($event) => $event->getAggregateId() === 'order-123'
);
```

## Domain Assertions

Use the `DomainAssertions` trait for domain-specific assertions:

```php
<?php

use Lumina\DDD\Testing\Assertions\DomainAssertions;

final class OrderTest extends TestCase
{
    use DomainAssertions;

    public function testOrderRecordsEvent(): void
    {
        $order = Order::place(new CustomerId('customer-1'));
        $order->submit();

        self::assertAggregateHasEvent($order, OrderSubmittedEvent::class);
    }

    public function testEntitiesAreEqual(): void
    {
        $user1 = new User('user-123', 'John');
        $user2 = new User('user-123', 'John Doe');

        self::assertEntitiesEqual($user1, $user2);
    }

    public function testValueObjectsAreEqual(): void
    {
        $money1 = Money::fromCents(1000, 'USD');
        $money2 = Money::fromCents(1000, 'USD');

        self::assertValueObjectsEqual($money1, $money2);
    }
}
```

## Entity Factories

Create entity factories for test data:

```php
<?php

declare(strict_types=1);

namespace Tests\Factories;

use Lumina\DDD\Testing\Factory\EntityFactory;
use App\Domain\Entities\User;

final class UserFactory extends EntityFactory
{
    protected function getEntityClass(): string
    {
        return User::class;
    }

    protected function definition(): array
    {
        return [
            'id' => $this->generateId(),
            'name' => $this->fakeName(),
            'email' => $this->fakeEmail(),
            'status' => UserStatus::Active,
        ];
    }

    protected function make(array $attributes): User
    {
        return new User(
            id: $attributes['id'],
            name: $attributes['name'],
            email: $attributes['email'],
            status: $attributes['status'],
        );
    }

    public function inactive(): static
    {
        return $this->with('status', UserStatus::Inactive);
    }

    public function admin(): static
    {
        return $this->withAttributes([
            'email' => 'admin@example.com',
            'role' => UserRole::Admin,
        ]);
    }
}

// Usage
$user = UserFactory::new()->create();
$inactiveUser = UserFactory::new()->inactive()->create();
$admin = UserFactory::new()->admin()->create();
$users = UserFactory::new()->createMany(5);
```

## Testing Commands and Queries

### Testing Command Handlers

```php
<?php

final class CreateProductCommandHandlerTest extends TestCase
{
    public function testCreatesProduct(): void
    {
        // Arrange
        $repository = new InMemoryProductRepository();
        $handler = new CreateProductCommandHandler($repository);
        
        $command = new CreateProductCommand(
            name: 'Widget',
            price: 1999,
            sku: 'WDG-001',
        );

        // Act
        $productId = $handler($command);

        // Assert
        $product = $repository->findById($productId);
        $this->assertNotNull($product);
        $this->assertSame('Widget', $product->getName());
        $this->assertSame(1999, $product->getPrice()->getAmount());
    }

    public function testThrowsOnDuplicateSku(): void
    {
        $repository = new InMemoryProductRepository();
        $repository->save(Product::create('Existing', Money::fromCents(999), 'WDG-001'));
        
        $handler = new CreateProductCommandHandler($repository);
        $command = new CreateProductCommand(
            name: 'Widget',
            price: 1999,
            sku: 'WDG-001', // Duplicate
        );

        $this->expectException(DuplicateSkuException::class);
        
        $handler($command);
    }
}
```

### Testing Query Handlers

```php
<?php

final class GetProductQueryHandlerTest extends TestCase
{
    public function testReturnsProductDto(): void
    {
        // Arrange
        $product = Product::create('Widget', Money::fromCents(1999), 'WDG-001');
        $repository = new InMemoryProductRepository();
        $repository->save($product);
        
        $handler = new GetProductQueryHandler($repository);
        $query = new GetProductQuery($product->getId());

        // Act
        $result = $handler($query);

        // Assert
        $this->assertInstanceOf(ProductDto::class, $result);
        $this->assertSame('Widget', $result->name);
    }

    public function testReturnsNullForNonExistentProduct(): void
    {
        $repository = new InMemoryProductRepository();
        $handler = new GetProductQueryHandler($repository);
        
        $result = $handler(new GetProductQuery('non-existent'));

        $this->assertNull($result);
    }
}
```

## Testing Aggregates

```php
<?php

final class OrderTest extends TestCase
{
    use DomainAssertions;

    public function testCanPlaceOrder(): void
    {
        $order = Order::place(new CustomerId('customer-1'));

        self::assertEntityNotTransient($order);
        self::assertAggregateHasEvent($order, OrderPlacedEvent::class);
    }

    public function testCanAddLineItems(): void
    {
        $order = Order::place(new CustomerId('customer-1'));
        $product = Product::create('Widget', Money::fromCents(999), 'WDG-001');

        $order->addLine($product, 2);

        $this->assertCount(1, $order->getLines());
        self::assertAggregateHasEvent($order, OrderLineAddedEvent::class);
    }

    public function testCannotAddLineToSubmittedOrder(): void
    {
        $order = Order::place(new CustomerId('customer-1'));
        $product = Product::create('Widget', Money::fromCents(999), 'WDG-001');
        $order->addLine($product, 1);
        $order->submit();

        $this->expectException(OrderAlreadySubmittedException::class);

        $order->addLine($product, 1);
    }
}
```

## Best Practices

1. **Use in-memory implementations**: Test logic, not infrastructure
2. **One assertion per test**: Keep tests focused and readable
3. **Use factories**: Create consistent test data
4. **Test edge cases**: Cover error conditions and boundaries
5. **Name tests descriptively**: Use `testCanDoSomething` or `testThrowsWhenInvalid`
6. **Arrange-Act-Assert**: Structure tests clearly
