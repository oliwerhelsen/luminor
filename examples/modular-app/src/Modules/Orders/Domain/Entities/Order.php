<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders\Domain\Entities;

use Luminor\Domain\Abstractions\AggregateRoot;
use Example\ModularApp\Modules\Orders\Domain\Events\OrderPlacedEvent;
use Example\ModularApp\Modules\Orders\Domain\Events\OrderCompletedEvent;
use Example\ModularApp\Modules\Orders\Domain\ValueObjects\OrderStatus;

/**
 * Order aggregate root.
 */
final class Order extends AggregateRoot
{
    /** @var OrderLine[] */
    private array $lines = [];

    private function __construct(
        string $id,
        private readonly string $customerId,
        private OrderStatus $status,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id);
    }

    /**
     * Place a new order.
     */
    public static function place(string $customerId): self
    {
        $id = self::generateId();
        $order = new self(
            $id,
            $customerId,
            OrderStatus::Pending,
            new \DateTimeImmutable()
        );
        
        return $order;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    /**
     * @return OrderLine[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Add a line item to the order.
     */
    public function addLine(string $productId, string $productName, int $quantity, int $unitPriceInCents): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new \InvalidArgumentException('Cannot modify a non-pending order');
        }
        
        $this->lines[] = new OrderLine(
            productId: $productId,
            productName: $productName,
            quantity: $quantity,
            unitPriceInCents: $unitPriceInCents,
        );
    }

    /**
     * Submit the order for processing.
     */
    public function submit(): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new \InvalidArgumentException('Order is not pending');
        }
        
        if (empty($this->lines)) {
            throw new \InvalidArgumentException('Order has no line items');
        }
        
        $this->status = OrderStatus::Submitted;
        
        // Record event for other modules to react to
        $items = array_map(fn($line) => [
            'productId' => $line->getProductId(),
            'quantity' => $line->getQuantity(),
        ], $this->lines);
        
        $this->recordEvent(new OrderPlacedEvent(
            $this->getId(),
            $this->customerId,
            $items,
            $this->getTotalInCents()
        ));
    }

    /**
     * Complete the order.
     */
    public function complete(): void
    {
        if ($this->status !== OrderStatus::Submitted) {
            throw new \InvalidArgumentException('Order must be submitted to complete');
        }
        
        $this->status = OrderStatus::Completed;
        $this->recordEvent(new OrderCompletedEvent($this->getId()));
    }

    /**
     * Cancel the order.
     */
    public function cancel(): void
    {
        if ($this->status === OrderStatus::Completed) {
            throw new \InvalidArgumentException('Cannot cancel a completed order');
        }
        
        $this->status = OrderStatus::Cancelled;
    }

    /**
     * Get total order value in cents.
     */
    public function getTotalInCents(): int
    {
        return array_reduce(
            $this->lines,
            fn(int $total, OrderLine $line) => $total + $line->getTotalInCents(),
            0
        );
    }
}
