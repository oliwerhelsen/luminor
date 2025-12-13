---
title: Best Practices
layout: default
nav_order: 14
description: "Comprehensive guide to building robust Luminor applications"
permalink: /best-practices/
---

# Best Practices

A comprehensive guide to building maintainable, scalable, and secure applications with Luminor.

## Table of Contents

- [Architectural Principles](#architectural-principles)
- [Domain Layer Guidelines](#domain-layer-guidelines)
- [Application Layer Patterns](#application-layer-patterns)
- [HTTP Layer Standards](#http-layer-standards)
- [Project Organization](#project-organization)
- [Common Anti-Patterns](#common-anti-patterns)
- [Performance & Scalability](#performance--scalability)
- [Security Considerations](#security-considerations)
- [Testing Strategies](#testing-strategies)
- [Production Readiness](#production-readiness)

---

## Architectural Principles

### Follow the Dependency Rule

Dependencies should always point inward. The domain layer should have no knowledge of outer layers.

**Good:**
```php
// Domain entity - no framework dependencies
final class Order extends AggregateRoot
{
    public function complete(): void
    {
        $this->recordEvent(new OrderCompleted($this->id));
    }
}
```

**Bad:**
```php
// Domain entity with infrastructure dependency
final class Order extends AggregateRoot
{
    public function __construct(
        private LoggerInterface $logger // ❌ Infrastructure dependency
    ) {}
}
```

### Use Ubiquitous Language

Name your classes, methods, and variables using terms from the business domain, not technical jargon.

**Good:**
```php
final readonly class PlaceOrderCommand implements Command
{
    public function __construct(
        public string $customerId,
        public array $items,
    ) {}
}

final class PlaceOrderHandler implements CommandHandler
{
    public function handle(PlaceOrderCommand $command): string
    {
        $order = Order::create(new CustomerId($command->customerId));

        foreach ($command->items as $item) {
            $order->addItem($item['productId'], $item['quantity']);
        }

        $this->repository->save($order);
        return $order->getId();
    }
}
```

**Bad:**
```php
class CreateDataCommand implements Command {}
class ProcessorService {}
public function setData($data): void {
    $this->status = 'processed';
}
```

### Respect Bounded Contexts

Large applications should be organized into modules with clear boundaries. Each module should represent a bounded context.

```php
// Modules represent different bounded contexts
app/
├── Modules/
│   ├── Sales/          # Sales context
│   ├── Inventory/      # Inventory context
│   └── Shipping/       # Shipping context
```

[Learn more about Modules →](modules#best-practices)

---

## Domain Layer Guidelines

### Keep Domain Pure

The domain layer should contain only business logic—no I/O, no framework code, no infrastructure concerns.

**Do:**
- Pure PHP classes and interfaces
- Business rules and validations
- Domain events
- Domain exceptions

**Don't:**
- Database queries
- HTTP requests
- File system operations
- Framework-specific code

[Learn more about Domain Layer →](domain-layer#best-practices)

### Choose Between Entity and Value Object

Use this decision framework:

**Use an Entity when:**
- The object has a unique identity
- Identity matters more than attributes
- The object's state changes over time
- Example: `User`, `Order`, `Product`

**Use a Value Object when:**
- Identity doesn't matter—two objects with same attributes are equal
- The object is immutable
- The object describes or quantifies something
- Example: `Money`, `Email`, `Address`, `DateRange`

```php
// Entity - identity matters
final class User extends Entity
{
    private function __construct(
        string $id,
        private Email $email,
        private string $name,
    ) {
        parent::__construct($id);
    }
}

// Value Object - attributes define equality
final readonly class Email
{
    public function __construct(
        private string $value,
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }
}
```

### Protect Invariants

Entities should always be in a valid state. Use constructors and named constructors to enforce invariants.

**Good:**
```php
final class Order extends AggregateRoot
{
    private function __construct(
        string $id,
        private CustomerId $customerId,
        private Money $total,
        private OrderStatus $status,
    ) {
        parent::__construct($id);
    }

    public static function create(CustomerId $customerId): self
    {
        $order = new self(
            self::generateId(),
            $customerId,
            Money::zero('USD'),
            OrderStatus::PENDING,
        );

        $order->recordEvent(new OrderCreated($order->id));
        return $order;
    }

    public function addItem(OrderItem $item): void
    {
        if (!$this->status->is(OrderStatus::PENDING)) {
            throw new OrderAlreadyProcessed();
        }

        // Add item logic...
    }
}
```

**Bad:**
```php
// Anemic entity - no business logic
final class Order extends AggregateRoot
{
    public function __construct(
        public string $id,
        public string $customerId,
        public int $total,
        public string $status, // ❌ No type safety
    ) {}

    // Setters everywhere - no invariant protection
    public function setTotal(int $total): void
    {
        $this->total = $total;
    }
}
```

### Use Domain Events

Record events for significant business occurrences. This enables loose coupling and audit trails.

```php
final class Order extends AggregateRoot
{
    private OrderStatus $status;
    private ?TrackingNumber $trackingNumber = null;

    public function ship(TrackingNumber $trackingNumber): void
    {
        if (!$this->canBeShipped()) {
            throw new OrderCannotBeShipped();
        }

        $this->status = OrderStatus::SHIPPED;
        $this->trackingNumber = $trackingNumber;

        $this->recordEvent(new OrderShipped(
            orderId: $this->id,
            trackingNumber: $trackingNumber,
            shippedAt: new DateTimeImmutable(),
        ));
    }
}
```

---

## Application Layer Patterns

### Commands vs Queries

Follow CQRS strictly: commands change state, queries read state.

**Commands:**
- Represent user intentions
- Modify system state
- Return void or an identifier
- May raise domain events
- Should be validated

```php
// Command - changes state
final readonly class CreateProductCommand implements Command
{
    public function __construct(
        public string $name,
        public int $priceInCents,
        public string $sku,
    ) {}
}

// Handler returns ID only
final class CreateProductHandler implements CommandHandler
{
    public function handle(CreateProductCommand $command): string
    {
        $product = Product::create(
            $command->name,
            Money::fromCents($command->priceInCents),
            new SKU($command->sku)
        );

        $this->repository->save($product);

        return $product->getId();
    }
}
```

**Queries:**
- Read data without side effects
- Return DTOs or read models
- Can be optimized independently
- No business logic—just data retrieval

```php
// Query - reads data
final readonly class GetProductQuery implements Query
{
    public function __construct(
        public string $productId,
    ) {}
}

// Handler returns DTO
final class GetProductHandler implements QueryHandler
{
    public function handle(GetProductQuery $query): ProductDTO
    {
        $product = $this->repository->findById($query->productId);

        if ($product === null) {
            throw new ProductNotFound($query->productId);
        }

        return ProductDTO::fromEntity($product);
    }
}
```

[Learn more about Application Layer →](application-layer#best-practices)

### Validate Input Early

Always validate commands before executing business logic.

```php
final class CreateProductHandler implements CommandHandler
{
    public function handle(CreateProductCommand $command): string
    {
        // Validate first
        $this->validator->validate($command, [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'priceInCents' => ['required', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'unique:products,sku'],
        ]);

        // Then execute
        $product = Product::create(/* ... */);
        $this->repository->save($product);

        return $product->getId();
    }
}
```

[Learn more about Validation →](validation#best-practices)

### Use DTOs for Data Transfer

Don't expose entities or domain objects directly through APIs. Use DTOs.

```php
// DTO for API responses
final readonly class ProductDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public int $priceInCents,
        public string $currency,
        public string $sku,
        public string $createdAt,
    ) {}

    public static function fromEntity(Product $product): self
    {
        return new self(
            $product->getId(),
            $product->getName(),
            $product->getPrice()->getCents(),
            $product->getPrice()->getCurrency(),
            $product->getSku()->getValue(),
            $product->getCreatedAt()->format('c'),
        );
    }
}
```

---

## HTTP Layer Standards

### Keep Controllers Thin

Controllers should orchestrate, not contain business logic.

**Good:**
```php
final class ProductController extends ApiController
{
    public function store(Request $request, Response $response): Response
    {
        $productId = $this->commandBus->dispatch(
            new CreateProductCommand(
                name: $request->getPayload()['name'],
                priceInCents: $request->getPayload()['price'],
                sku: $request->getPayload()['sku'],
            )
        );

        return $this->created($response, ['id' => $productId]);
    }
}
```

**Bad:**
```php
final class ProductController extends ApiController
{
    public function store(Request $request, Response $response): Response
    {
        // ❌ Validation in controller
        if (empty($request->getPayload()['name'])) {
            return $this->badRequest($response, 'Name required');
        }

        // ❌ Business logic in controller
        $product = new Product();
        $product->setName($request->getPayload()['name']);
        $product->setPrice($request->getPayload()['price']);

        // ❌ Direct repository access
        $this->repository->save($product);

        return $this->created($response);
    }
}
```

### Use Middleware for Cross-Cutting Concerns

Authentication, logging, rate limiting, etc., belong in middleware, not controllers.

```php
$app->group('/api', function (App $app) {
    $app->group('/admin', function (App $app) {
        $app->get('/users', [UserController::class, 'index']);
    })->middleware([
        AuthenticationMiddleware::class,
        RoleMiddleware::class(['admin']),
    ]);
})->middleware([
    RateLimitMiddleware::class,
    CorsMiddleware::class,
]);
```

[Learn more about HTTP Layer →](http-layer#best-practices)

---

## Project Organization

### Standard Directory Structure

Follow a consistent structure for all Luminor projects:

```
app/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Events/
│   ├── Exceptions/
│   ├── Repositories/
│   └── Specifications/
├── Application/
│   ├── Commands/
│   ├── Queries/
│   ├── Handlers/
│   ├── DTOs/
│   └── Services/
├── Infrastructure/
│   ├── Persistence/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── routes/
│   └── Events/
└── Modules/
    ├── Sales/
    ├── Inventory/
    └── Shipping/
```

### Module Organization

For large applications, organize by modules (bounded contexts):

```
app/Modules/Sales/
├── Domain/
│   ├── Order.php
│   ├── OrderItem.php
│   └── Events/
│       └── OrderPlaced.php
├── Application/
│   ├── Commands/
│   │   └── PlaceOrderCommand.php
│   └── Handlers/
│       └── PlaceOrderHandler.php
├── Infrastructure/
│   ├── Http/
│   │   └── OrderController.php
│   └── Persistence/
│       └── OrderRepository.php
└── SalesServiceProvider.php
```

### Naming Conventions

Be consistent with naming across your application:

| Type | Convention | Example |
|------|-----------|---------|
| Entity | Singular noun | `Product`, `Order` |
| Value Object | Descriptive noun | `Money`, `Email`, `Address` |
| Command | Verb + noun | `CreateProduct`, `PlaceOrder` |
| Query | Get/Find/List + noun | `GetProduct`, `ListOrders` |
| Handler | Command/Query + Handler | `CreateProductHandler` |
| Event | Past tense verb + noun | `ProductCreated`, `OrderShipped` |
| Exception | Descriptive + Exception | `ProductNotFound`, `InsufficientStock` |
| DTO | Noun + DTO | `ProductDTO`, `OrderDTO` |
| Repository | Noun + Repository | `ProductRepository` |

---

## Common Anti-Patterns

### Anemic Domain Model

**Problem:** Entities with only getters/setters and no behavior.

**Bad:**
```php
class Order
{
    private array $items = [];

    public function getItems(): array { return $this->items; }
    public function setItems(array $items): void { $this->items = $items; }
}

// Business logic in service instead of domain
class OrderService
{
    public function calculateTotal(Order $order): Money
    {
        $total = 0;
        foreach ($order->getItems() as $item) {
            // ❌ Money objects and primitives mixed
            // ❌ Business logic outside domain
            $total += $item->getPrice()->getCents() * $item->getQuantity();
        }
        return Money::fromCents($total);
    }
}
```

**Good:**
```php
class Order extends AggregateRoot
{
    private array $items = [];

    public function addItem(OrderItem $item): void
    {
        $this->items[] = $item;
        $this->recordEvent(new OrderItemAdded($this->id, $item));
    }

    public function getTotal(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $total, OrderItem $item) => $total->add($item->getSubtotal()),
            Money::zero('USD')
        );
    }
}
```

### God Objects

**Problem:** Classes that know too much or do too much.

**Bad:**
```php
class OrderService
{
    public function processOrder(array $data): void
    {
        // Validation
        // Business logic
        // Email sending
        // Inventory updates
        // Payment processing
        // Shipping coordination
        // Analytics tracking
        // ...hundreds of lines
    }
}
```

**Good:**
```php
// Separate concerns into focused handlers
class PlaceOrderHandler implements CommandHandler {}
class ProcessPaymentHandler implements CommandHandler {}
class UpdateInventoryHandler implements EventHandler {}
class SendOrderConfirmationHandler implements EventHandler {}
```

### Leaky Abstractions

**Problem:** Domain layer depending on infrastructure concerns.

**Bad:**
```php
// Domain entity with HTTP dependency
class User extends Entity
{
    public function notifyByEmail(string $message): void
    {
        $mailer = new SmtpMailer(); // ❌ Infrastructure in domain
        $mailer->send($this->email, $message);
    }
}
```

**Good:**
```php
// Domain entity raises event
class User extends Entity
{
    public function changeEmail(Email $newEmail): void
    {
        $this->email = $newEmail;
        $this->recordEvent(new UserEmailChanged($this->id, $newEmail));
    }
}

// Infrastructure handles the event
class NotifyUserOnEmailChange implements EventHandler
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function handle(UserEmailChanged $event): void
    {
        $this->mailer->send(/* ... */);
    }
}
```

### Primitive Obsession

**Problem:** Using primitive types instead of value objects.

**Bad:**
```php
class Product extends Entity
{
    public function __construct(
        private string $email, // ❌ No validation
        private int $price,    // ❌ What currency?
        private string $phone, // ❌ What format?
    ) {}
}
```

**Good:**
```php
class Product extends Entity
{
    public function __construct(
        private Email $email,
        private Money $price,
        private PhoneNumber $phone,
    ) {}
}
```

---

## Performance & Scalability

### Optimize Queries

Use pagination, filtering, and proper indexing for list queries.

```php
enum SortOrder: string
{
    case ASC = 'asc';
    case DESC = 'desc';
}

final class ListProductsQuery implements Query
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly ?string $category = null,
        public readonly ?string $sortBy = 'created_at',
        public readonly SortOrder $sortOrder = SortOrder::DESC,
    ) {}
}

final class ListProductsHandler implements QueryHandler
{
    public function handle(ListProductsQuery $query): PaginatedResult
    {
        $builder = $this->repository->query();

        if ($query->category) {
            $builder->where('category', $query->category);
        }

        return $builder
            ->orderBy($query->sortBy, $query->sortOrder)
            ->paginate($query->page, $query->perPage);
    }
}
```

### Use Caching Strategically

Cache expensive queries and computations, but invalidate correctly.

```php
final class GetProductHandler implements QueryHandler
{
    public function handle(GetProductQuery $query): ProductDTO
    {
        return $this->cache->remember(
            key: "product:{$query->productId}",
            ttl: 3600,
            callback: fn() => $this->repository->findById($query->productId)
        );
    }
}

// Invalidate on updates
final class UpdateProductHandler implements CommandHandler
{
    public function handle(UpdateProductCommand $command): void
    {
        $product = $this->repository->findById($command->productId);
        $product->update(/* ... */);
        $this->repository->save($product);

        // Invalidate cache
        $this->cache->forget("product:{$command->productId}");
    }
}
```

[Learn more about Caching →](cache#best-practices)

### Use Queues for Async Operations

Don't block HTTP requests with slow operations.

```php
final class PlaceOrderHandler implements CommandHandler
{
    public function handle(PlaceOrderCommand $command): string
    {
        $order = Order::create(/* ... */);
        $this->repository->save($order);

        // Queue slow operations
        $this->queue->dispatch(new SendOrderConfirmation($order->getId()));
        $this->queue->dispatch(new UpdateInventory($order->getId()));
        $this->queue->dispatch(new NotifyShipping($order->getId()));

        return $order->getId();
    }
}
```

[Learn more about Queues →](queues)

### Consider Read Models for Complex Queries

For complex reporting or analytics, use dedicated read models instead of querying the write model.

```php
// Write model (normalized, rich domain)
class Order extends AggregateRoot { /* ... */ }

// Read model (denormalized, optimized for queries)
final readonly class OrderReportView
{
    public function __construct(
        public string $orderId,
        public string $customerName,
        public string $customerEmail,
        public int $totalInCents,
        public int $itemCount,
        public string $status,
        public string $createdAt,
    ) {}
}

// Projection: update read model when events occur
final class UpdateOrderReportViewOnOrderPlaced implements EventHandler
{
    public function handle(OrderPlaced $event): void
    {
        // Update denormalized view for fast querying
        $this->database->insert('order_report_views', [
            'order_id' => $event->orderId,
            'customer_name' => $event->customerName,
            // ... other denormalized data
        ]);
    }
}
```

---

## Security Considerations

### Always Validate Input

Never trust user input. Validate all commands and requests.

```php
final class CreateProductHandler implements CommandHandler
{
    public function handle(CreateProductCommand $command): string
    {
        $this->validator->validate($command, [
            'name' => ['required', 'string', 'max:255'],
            'priceInCents' => ['required', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'regex:/^[A-Z0-9\-]+$/'],
        ]);

        // Safe to proceed
    }
}
```

### Use Authorization Policies

Check permissions before executing sensitive operations.

```php
final readonly class DeleteProductCommand implements Command
{
    public function __construct(
        public string $productId,
        public string $userId,
    ) {}
}

final class DeleteProductHandler implements CommandHandler
{
    public function handle(DeleteProductCommand $command): void
    {
        $product = $this->repository->findById($command->productId);

        if ($product === null) {
            throw new ProductNotFound($command->productId);
        }

        // Check authorization
        if (!$this->policy->can($command->userId, 'delete', $product)) {
            throw new UnauthorizedException();
        }

        $this->repository->delete($product);
        $product->recordEvent(new ProductDeleted($product->getId()));
        $this->repository->save($product);
    }
}
```

### Protect Against Common Vulnerabilities

- **SQL Injection**: Use parameterized queries (repositories handle this)
- **XSS**: Sanitize output, use Content-Security-Policy headers
- **CSRF**: Use CSRF tokens for state-changing operations
- **Mass Assignment**: Use DTOs/Commands with explicit properties
- **Authentication**: Hash passwords, use secure session configuration

[Learn more about Security →](security#security-best-practices)

### Secure Session Configuration

```php
return [
    'driver' => 'file',
    'lifetime' => 7200,
    'expire_on_close' => true,
    'cookie' => [
        'name' => 'luminor_session',
        'path' => '/',
        'domain' => null,
        'secure' => true,      // HTTPS only
        'httponly' => true,    // No JavaScript access
        'samesite' => 'Lax',   // CSRF protection
    ],
];
```

[Learn more about Sessions →](session#best-practices)

### Use Environment Variables for Secrets

Never commit secrets to version control.

```php
// config/database.php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'host' => env('DB_HOST', 'localhost'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
        ],
    ],
];
```

---

## Testing Strategies

### Test at Multiple Levels

```
Unit Tests (Fast, Isolated)
    ↓
Integration Tests (Components together)
    ↓
Feature Tests (HTTP endpoints)
```

**Unit Tests:**
```php
final class OrderTest extends TestCase
{
    public function testCannotAddItemToCompletedOrder(): void
    {
        $order = Order::create(new CustomerId('123'));
        $order->complete();

        $this->expectException(OrderAlreadyCompleted::class);
        $order->addItem(new OrderItem(/* ... */));
    }
}
```

**Integration Tests:**
```php
final class PlaceOrderHandlerTest extends TestCase
{
    public function testPlacesOrderSuccessfully(): void
    {
        $handler = new PlaceOrderHandler(
            $this->createMock(OrderRepository::class),
            new InMemoryEventBus(),
        );

        $orderId = $handler->handle(new PlaceOrderCommand(
            customerId: '123',
            items: [['productId' => 'p1', 'quantity' => 2]],
        ));

        $this->assertNotEmpty($orderId);
    }
}
```

**Feature Tests:**
```php
final class ProductApiTest extends TestCase
{
    public function testCreatesProduct(): void
    {
        $response = $this->post('/api/products', [
            'name' => 'Widget',
            'price' => 2999,
            'sku' => 'WID-001',
        ]);

        $this->assertResponseStatus($response, 201);
        $this->assertJsonHasKey($response, 'data.id');
    }
}
```

[Learn more about Testing →](testing#best-practices)

### Use In-Memory Implementations for Tests

```php
final class PlaceOrderHandlerTest extends TestCase
{
    private CommandHandler $handler;
    private InMemoryEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new InMemoryEventBus();
        $this->handler = new PlaceOrderHandler(
            new InMemoryOrderRepository(),
            $this->eventBus,
        );
    }

    public function testRaisesOrderPlacedEvent(): void
    {
        $orderId = $this->handler->handle(new PlaceOrderCommand(/* ... */));

        $this->eventBus->assertDispatched(OrderPlaced::class);
        $this->eventBus->assertDispatched(
            OrderPlaced::class,
            fn($event) => $event->orderId === $orderId
        );
    }
}
```

### Test Business Logic, Not Infrastructure

Focus tests on domain logic and behavior, not on framework features.

**Good:**
```php
public function testCalculatesTotalCorrectly(): void
{
    $order = Order::create(new CustomerId('123'));
    $order->addItem(new OrderItem(Money::fromCents(1000), 2));
    $order->addItem(new OrderItem(Money::fromCents(500), 1));

    $this->assertEquals(2500, $order->getTotal()->getCents());
}
```

**Less useful:**
```php
public function testDatabaseConnectionWorks(): void
{
    $this->assertInstanceOf(PDO::class, $this->database->getConnection());
}
```

---

## Production Readiness

### Environment Configuration Checklist

Before deploying to production:

- [ ] Set `APP_ENV=production`
- [ ] Use strong `APP_KEY` for encryption
- [ ] Configure secure session settings
- [ ] Enable HTTPS only
- [ ] Set proper CORS policies
- [ ] Configure error logging (don't expose stack traces)
- [ ] Set up monitoring and alerting
- [ ] Use environment variables for all secrets
- [ ] Enable rate limiting
- [ ] Configure database connection pooling
- [ ] Set up automated backups
- [ ] Enable CSRF protection
- [ ] Configure proper cache drivers (Redis, Memcached)
- [ ] Set up queue workers
- [ ] Configure CDN for static assets

### Error Handling

Log errors but don't expose details to users in production.

```php
use Luminor\Logging\LoggerInterface;

final class GlobalExceptionHandler
{
    public function handle(Throwable $e, Response $response): Response
    {
        $this->logger->error('Unhandled exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($this->config->get('app.env') === 'production') {
            return $response
                ->setStatusCode(500)
                ->json(['error' => 'Internal server error']);
        }

        return $response
            ->setStatusCode(500)
            ->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);
    }
}
```

[Learn more about Logging →](logging)

### Database Migrations

Always use migrations for schema changes—never modify the database manually.

```bash
# Good: versioned, reversible, trackable
php bin/luminor migrate

# Bad: manual changes
mysql> ALTER TABLE products ADD COLUMN sku VARCHAR(255);
```

[Learn more about Database →](database#best-practices)

### Monitoring and Observability

Track key metrics:

```php
final class PlaceOrderHandler implements CommandHandler
{
    public function handle(PlaceOrderCommand $command): string
    {
        $startTime = microtime(true);

        try {
            $orderId = $this->processOrder($command);

            $this->metrics->increment('orders.placed');
            $this->metrics->timing('orders.place_duration', microtime(true) - $startTime);

            return $orderId;
        } catch (Throwable $e) {
            $this->metrics->increment('orders.failed');
            throw $e;
        }
    }
}
```

---

## Quick Reference

### Decision Trees

**When should I create a new module?**
- The feature represents a distinct bounded context
- It has its own ubiquitous language
- It could potentially be extracted as a microservice
- The team structure aligns with the module boundary

**Command or Query?**
- Changes state → Command
- Reads data → Query

**Entity or Value Object?**
- Has unique identity → Entity
- Defined by attributes → Value Object

**Where does this logic belong?**
- Business rule → Domain Layer
- Orchestration → Application Layer (Handler)
- HTTP concern → HTTP Layer (Controller/Middleware)
- External system → Infrastructure Layer

### Common Commands

```bash
# Generate domain entity
php luminor make:entity Product

# Generate command and handler
php luminor make:command CreateProduct

# Generate query and handler
php luminor make:query GetProduct

# Generate complete module
php luminor make:module Sales

# Run tests
composer test

# Run static analysis
composer analyse
```

### Additional Resources

- [Getting Started](getting-started) - Installation and setup
- [Core Concepts](core-concepts) - Architecture overview
- [Domain Layer](domain-layer) - Entities, value objects, events
- [Application Layer](application-layer) - Commands, queries, handlers
- [HTTP Layer](http-layer) - Controllers, routing, middleware
- [Modules](modules) - Bounded contexts and modular architecture
- [Testing](testing) - Testing strategies and utilities
- [Security](security) - Security features and best practices
- [Database](database) - Migrations, schema, repositories
- [Authentication](AUTHENTICATION) - Auth patterns and tutorials

---

## Contributing

Found a best practice we should include? [Open an issue](https://github.com/luminor-php/luminor/issues) or submit a pull request!
