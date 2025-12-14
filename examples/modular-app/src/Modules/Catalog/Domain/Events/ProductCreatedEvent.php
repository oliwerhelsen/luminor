<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Catalog\Domain\Events;

use Luminor\Domain\Abstractions\DomainEvent;

/**
 * Event raised when a product is created.
 */
final class ProductCreatedEvent extends DomainEvent
{
    public function __construct(
        string $productId,
        public readonly string $name,
        public readonly int $priceInCents,
        public readonly string $currency,
    ) {
        parent::__construct($productId);
    }
}
