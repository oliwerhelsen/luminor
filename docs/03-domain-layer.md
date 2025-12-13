# Domain Layer

The domain layer is the heart of your application, containing all business logic and rules. This guide covers the DDD building blocks provided by the framework.

## Entities

Entities are objects with a unique identity that persists over time.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use Luminor\DDD\Domain\Abstractions\Entity;

final class Product extends Entity
{
    public function __construct(
        string $id,
        private string $name,
        private Money $price,
        private int $stock,
    ) {
        parent::__construct($id);
    }

    public static function create(string $name, Money $price, int $stock = 0): self
    {
        return new self(self::generateId(), $name, $price, $stock);
    }

    public function adjustStock(int $quantity): void
    {
        $newStock = $this->stock + $quantity;
        
        if ($newStock < 0) {
            throw new InsufficientStockException($this, abs($quantity));
        }
        
        $this->stock = $newStock;
    }
}
```

## Aggregate Roots

Aggregate roots are special entities that serve as entry points to a cluster of related objects.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use Luminor\DDD\Domain\Abstractions\AggregateRoot;

final class Order extends AggregateRoot
{
    /** @var OrderLine[] */
    private array $lines = [];
    private OrderStatus $status;

    public function __construct(
        string $id,
        private readonly CustomerId $customerId,
    ) {
        parent::__construct($id);
        $this->status = OrderStatus::Pending;
    }

    public static function place(CustomerId $customerId): self
    {
        $order = new self(self::generateId(), $customerId);
        $order->recordEvent(new OrderPlacedEvent($order->getId(), $customerId));
        return $order;
    }

    public function addLine(Product $product, int $quantity): void
    {
        $this->ensureNotSubmitted();
        
        $line = new OrderLine($product->getId(), $quantity, $product->getPrice());
        $this->lines[] = $line;
        
        $this->recordEvent(new OrderLineAddedEvent(
            $this->getId(),
            $product->getId(),
            $quantity
        ));
    }

    public function submit(): void
    {
        $this->ensureNotSubmitted();
        
        if (empty($this->lines)) {
            throw new EmptyOrderException();
        }
        
        $this->status = OrderStatus::Submitted;
        $this->recordEvent(new OrderSubmittedEvent($this->getId()));
    }

    private function ensureNotSubmitted(): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new OrderAlreadySubmittedException($this->getId());
        }
    }
}
```

## Value Objects

Value objects are immutable objects that represent a concept based on their attributes.

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use Luminor\DDD\Domain\Abstractions\ValueObject;

final class Money extends ValueObject
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, strtoupper($currency));
    }

    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    protected function getEqualityComponents(): array
    {
        return [$this->amount, $this->currency];
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
```

## Domain Events

Domain events capture something important that happened in the domain.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Events;

use Luminor\DDD\Domain\Abstractions\DomainEvent;

final class OrderSubmittedEvent extends DomainEvent
{
    public function __construct(
        string $orderId,
        public readonly Money $total,
    ) {
        parent::__construct($orderId);
    }
}
```

## Specifications

Specifications encapsulate business rules that can be combined.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Specifications;

use Luminor\DDD\Domain\Abstractions\Specification;
use App\Domain\Entities\Order;

final class HighValueOrderSpecification extends Specification
{
    public function __construct(
        private readonly Money $threshold,
    ) {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (!$candidate instanceof Order) {
            return false;
        }
        
        return $candidate->getTotal()->isGreaterThan($this->threshold);
    }
}

// Usage
$highValueSpec = new HighValueOrderSpecification(Money::fromCents(10000));
$rushSpec = new RushOrderSpecification();

// Combine specifications
$prioritySpec = $highValueSpec->or($rushSpec);

if ($prioritySpec->isSatisfiedBy($order)) {
    // Handle priority order
}
```

## Repositories

Repository interfaces define contracts for entity persistence.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use Luminor\DDD\Domain\Repository\RepositoryInterface;
use App\Domain\Entities\Order;

interface OrderRepositoryInterface extends RepositoryInterface
{
    public function findById(string $id): ?Order;
    
    public function findByCustomer(CustomerId $customerId): array;
    
    public function findPending(): array;
    
    public function save(Order $order): void;
}
```

## Domain Exceptions

Create domain-specific exceptions for business rule violations.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use Luminor\DDD\Domain\Abstractions\DomainException;

final class InsufficientStockException extends DomainException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $requestedQuantity,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for product %s. Requested: %d, Available: %d',
            $product->getName(),
            $requestedQuantity,
            $product->getStock()
        ));
    }
}
```

## Best Practices

1. **Keep the domain layer pure**: No framework dependencies, no I/O operations
2. **Use value objects**: For any concept that can be described by its attributes
3. **Encapsulate business rules**: In entities, specifications, or domain services
4. **Record events**: Use domain events to communicate state changes
5. **Protect invariants**: Ensure entities are always in a valid state
6. **Use ubiquitous language**: Name things as domain experts would
