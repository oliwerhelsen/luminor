---
title: Event Sourcing
layout: default
nav_order: 11
description: "Complete guide to event sourcing in Luminor"
permalink: /event-sourcing/
---

# Event Sourcing

Event sourcing is a powerful pattern where the state of your aggregates is derived from a sequence of events rather than storing the current state directly. Every state change is captured as an event, providing a complete audit trail and enabling powerful capabilities.

{: .highlight }
New in v2.0: Luminor now includes complete event sourcing support with event stores, snapshots, and projections.

## Table of Contents

- [Introduction](#introduction)
- [Core Concepts](#core-concepts)
- [Event Store](#event-store)
- [Event-Sourced Aggregates](#event-sourced-aggregates)
- [Snapshots](#snapshots)
- [Projections](#projections)
- [CLI Commands](#cli-commands)
- [Best Practices](#best-practices)

---

## Introduction

### Why Event Sourcing?

**Benefits:**
- **Complete Audit Trail** - Every state change is preserved forever
- **Temporal Queries** - Answer "what was the state on date X?"
- **Event Replay** - Rebuild state from events
- **Debugging** - Replay production issues locally
- **Business Insights** - Analyze historical events for patterns
- **Event-Driven Architecture** - Natural fit for microservices

**Trade-offs:**
- Increased complexity
- Cannot delete data easily (GDPR considerations)
- Eventual consistency in read models

### When to Use Event Sourcing

✅ **Good fit:**
- Financial systems requiring audit trails
- Systems with complex business workflows
- Applications needing temporal queries
- Event-driven architectures

❌ **Not ideal:**
- Simple CRUD applications
- Systems with strict GDPR delete requirements
- Teams unfamiliar with the pattern

---

## Core Concepts

### Events

Events represent facts about what happened in your domain.

```php
<?php

use Luminor\DDD\Domain\Abstractions\DomainEvent;

final class OrderPlaced extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private readonly string $customerId,
        private readonly array $items,
        private readonly int $totalInCents
    ) {
        parent::__construct($aggregateId);
    }

    public function getPayload(): array
    {
        return [
            'customerId' => $this->customerId,
            'items' => $this->items,
            'totalInCents' => $this->totalInCents,
        ];
    }
}
```

### Event Store

The event store persists all domain events in append-only fashion.

```php
<?php

use Luminor\DDD\Domain\Events\EventStoreInterface;

// Get events for an aggregate
$events = $eventStore->getEventsForAggregate($orderId);

// Get events by type
$events = $eventStore->getEventsByType(OrderPlaced::class);

// Get events in date range
$events = $eventStore->getEventsBetween($startDate, $endDate);
```

---

## Event Store

### Configuration

Configure the event store driver in `config/events.php`:

```php
<?php

return [
    'store' => [
        'driver' => env('EVENT_STORE_DRIVER', 'database'),
    ],
];
```

**Available drivers:**
- `database` - PostgreSQL, MySQL, SQLite (production)
- `memory` - In-memory storage (testing)

### Database Setup

Run the migration to create the event store tables:

```bash
php luminor migrate
```

This creates the `domain_events` and `snapshots` tables.

### Using the Event Store

```php
<?php

use Luminor\DDD\Domain\Events\EventStoreInterface;

class OrderService
{
    public function __construct(
        private readonly EventStoreInterface $eventStore
    ) {}

    public function getOrderHistory(string $orderId): array
    {
        return $this->eventStore->getEventsForAggregate($orderId);
    }
}
```

---

## Event-Sourced Aggregates

### Creating an Event-Sourced Aggregate

Extend `EventSourcedAggregateRoot` instead of `AggregateRoot`:

```php
<?php

use Luminor\DDD\Domain\Events\EventSourcedAggregateRoot;

final class Order extends EventSourcedAggregateRoot
{
    private string $customerId;
    private array $items = [];
    private int $totalInCents = 0;
    private OrderStatus $status;

    private function __construct(string $id)
    {
        parent::__construct($id);
    }

    // Factory method
    public static function place(
        string $customerId,
        array $items
    ): self {
        $order = new self(self::generateId());
        $order->recordEvent(new OrderPlaced(
            aggregateId: $order->getId(),
            customerId: $customerId,
            items: $items,
            totalInCents: self::calculateTotal($items)
        ));

        return $order;
    }

    // Apply method for OrderPlaced event
    protected function applyOrderPlaced(OrderPlaced $event): void
    {
        $payload = $event->getPayload();
        $this->customerId = $payload['customerId'];
        $this->items = $payload['items'];
        $this->totalInCents = $payload['totalInCents'];
        $this->status = OrderStatus::PENDING;
    }

    // Business method
    public function ship(string $trackingNumber): void
    {
        if (!$this->status->canShip()) {
            throw new OrderCannotBeShipped();
        }

        $this->recordEvent(new OrderShipped(
            aggregateId: $this->getId(),
            trackingNumber: $trackingNumber
        ));
    }

    // Apply method for OrderShipped event
    protected function applyOrderShipped(OrderShipped $event): void
    {
        $this->status = OrderStatus::SHIPPED;
    }

    private static function calculateTotal(array $items): int
    {
        return array_sum(array_map(
            fn($item) => $item['price'] * $item['quantity'],
            $items
        ));
    }
}
```

### Event-Sourced Repository

Create a repository that loads aggregates from events:

```php
<?php

use Luminor\DDD\Domain\Repository\EventSourcedRepository;

final class OrderRepository extends EventSourcedRepository
{
    protected function getAggregateClass(): string
    {
        return Order::class;
    }
}
```

### Usage

```php
<?php

// Create new order
$order = Order::place($customerId, $items);
$repository->save($order);

// Load from events
$order = $repository->findById($orderId);

// Modify
$order->ship($trackingNumber);
$repository->save($order);
```

---

## Snapshots

Snapshots improve performance by caching aggregate state, reducing event replay overhead.

### Configuration

```php
<?php

// config/events.php
return [
    'snapshots' => [
        'enabled' => true,
        'threshold' => 10, // Snapshot every 10 events
        'driver' => 'database',
    ],
];
```

### How Snapshots Work

1. After N events, a snapshot is taken
2. On load, the latest snapshot is retrieved
3. Only events after the snapshot are replayed
4. Dramatically faster for aggregates with many events

### Custom Snapshot Threshold

Override in your repository:

```php
<?php

final class OrderRepository extends EventSourcedRepository
{
    protected function shouldSnapshot(EventSourcedAggregateRoot $aggregate): bool
    {
        // Snapshot every 50 events for orders
        return $aggregate->getVersion() % 50 === 0;
    }
}
```

---

## Projections

Projections build read models from event streams, optimized for queries.

### Creating a Projector

```php
<?php

use Luminor\DDD\Domain\Events\AbstractProjector;

final class OrderSummaryProjector extends AbstractProjector
{
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {}

    public function getHandledEvents(): array
    {
        return [
            OrderPlaced::class,
            OrderShipped::class,
            OrderCancelled::class,
        ];
    }

    protected function whenOrderPlaced(OrderPlaced $event): void
    {
        $payload = $event->getPayload();

        $this->connection->execute(
            'INSERT INTO order_summaries (order_id, customer_id, total, status, created_at) VALUES (?, ?, ?, ?, ?)',
            [
                $event->getAggregateId(),
                $payload['customerId'],
                $payload['totalInCents'],
                'pending',
                $event->getOccurredOn()->format('Y-m-d H:i:s'),
            ]
        );
    }

    protected function whenOrderShipped(OrderShipped $event): void
    {
        $this->connection->execute(
            'UPDATE order_summaries SET status = ? WHERE order_id = ?',
            ['shipped', $event->getAggregateId()]
        );
    }

    protected function whenOrderCancelled(OrderCancelled $event): void
    {
        $this->connection->execute(
            'UPDATE order_summaries SET status = ? WHERE order_id = ?',
            ['cancelled', $event->getAggregateId()]
        );
    }

    public function reset(): void
    {
        $this->connection->execute('TRUNCATE TABLE order_summaries');
    }
}
```

### Registering Projectors

```php
<?php

use Luminor\DDD\Domain\Events\ProjectionManager;

// In your service provider
$projectionManager = $container->get(ProjectionManager::class);
$projectionManager->registerProjector(new OrderSummaryProjector($connection));
```

### Rebuilding Projections

Rebuild from the event store:

```bash
# Rebuild all projections
php luminor projection:rebuild --all

# Rebuild specific projection
php luminor projection:rebuild OrderSummaryProjector
```

---

## CLI Commands

### List Events

```bash
# List all events
php luminor events:list

# Filter by aggregate
php luminor events:list --aggregate=order-123

# Filter by type
php luminor events:list --type=OrderPlaced

# Limit results
php luminor events:list --limit=50
```

### Event Statistics

```bash
php luminor events:stats
```

Output:
```
Event Store Statistics
======================

Total Events: 1,543

Event Types:
  - OrderPlaced: 523
  - OrderShipped: 412
  - PaymentProcessed: 608

Unique Aggregates: 523
```

### Projection Management

```bash
# List projectors
php luminor projection:rebuild

# Rebuild all
php luminor projection:rebuild --all

# Rebuild one
php luminor projection:rebuild OrderSummaryProjector
```

---

## Best Practices

### Event Naming

✅ **Good:**
- Past tense: `OrderPlaced`, `PaymentProcessed`
- Business language: `CustomerRegistered` not `UserCreated`
- Specific: `OrderShipped` not `OrderUpdated`

❌ **Bad:**
- Present tense: `PlaceOrder`, `ProcessPayment`
- Technical: `DataInserted`, `RecordUpdated`
- Generic: `OrderChanged`, `OrderModified`

### Event Granularity

**Too Coarse:**
```php
class OrderUpdated extends DomainEvent {} // What changed?
```

**Too Fine:**
```php
class OrderTotalChanged extends DomainEvent {}
class OrderItemAdded extends DomainEvent {}
class OrderItemRemoved extends DomainEvent {}
class OrderItemQuantityChanged extends DomainEvent {}
```

**Just Right:**
```php
class OrderItemAdded extends DomainEvent {}
class OrderItemRemoved extends DomainEvent {}
class OrderItemUpdated extends DomainEvent {}
```

### Event Versioning

Plan for event evolution:

```php
<?php

final class OrderPlacedV2 extends DomainEvent
{
    // Add new fields with defaults
    public function __construct(
        string $aggregateId,
        private readonly string $customerId,
        private readonly array $items,
        private readonly int $totalInCents,
        private readonly ?string $promoCode = null // New field
    ) {
        parent::__construct($aggregateId);
    }
}
```

### Performance

- **Use snapshots** for aggregates with many events (>50)
- **Batch event writes** when possible
- **Index event store** on aggregate_id, event_type, occurred_on
- **Archive old events** to separate storage

### Testing

```php
<?php

use Luminor\DDD\Infrastructure\Persistence\InMemoryEventStore;

class OrderTest extends TestCase
{
    public function testOrderPlacement(): void
    {
        $order = Order::place($customerId, $items);

        // Verify events
        $events = $order->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderPlaced::class, $events[0]);
    }

    public function testReconstitution(): void
    {
        $events = [
            new OrderPlaced($orderId, $customerId, $items, 10000),
            new OrderShipped($orderId, 'TRACK123'),
        ];

        $order = Order::reconstituteFromEvents($events);

        $this->assertEquals(2, $order->getVersion());
        $this->assertEquals(OrderStatus::SHIPPED, $order->getStatus());
    }
}
```

---

## Next Steps

- [Domain Events](domain-layer#events)
- [CQRS](application-layer#cqrs)
- [Modules](modules)
- [Testing](testing)

## Additional Resources

- [Event Sourcing by Martin Fowler](https://martinfowler.com/eaaDev/EventSourcing.html)
- [CQRS Journey by Microsoft](https://docs.microsoft.com/en-us/previous-versions/msp-n-p/jj554200(v=pandp.10))
