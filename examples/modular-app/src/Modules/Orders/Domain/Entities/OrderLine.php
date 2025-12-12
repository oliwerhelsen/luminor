<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders\Domain\Entities;

/**
 * Order line item (value object within Order aggregate).
 */
final class OrderLine
{
    public function __construct(
        private readonly string $productId,
        private readonly string $productName,
        private readonly int $quantity,
        private readonly int $unitPriceInCents,
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
        
        if ($unitPriceInCents < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceInCents(): int
    {
        return $this->unitPriceInCents;
    }

    public function getTotalInCents(): int
    {
        return $this->quantity * $this->unitPriceInCents;
    }
}
