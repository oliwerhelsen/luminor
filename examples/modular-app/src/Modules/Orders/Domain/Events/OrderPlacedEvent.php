<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders\Domain\Events;

use Luminor\DDD\Domain\Abstractions\DomainEvent;

/**
 * Event raised when an order is placed.
 * This event is consumed by the Inventory module to reserve stock.
 */
final class OrderPlacedEvent extends DomainEvent
{
    public function __construct(
        string $orderId,
        public readonly string $customerId,
        /** @var array<array{productId: string, quantity: int}> */
        public readonly array $items,
        public readonly int $totalInCents,
    ) {
        parent::__construct($orderId);
    }
}
