<?php

declare(strict_types=1);

namespace Example\BasicApi\Domain\Entities;

use Luminor\Domain\Abstractions\Entity;
use Example\BasicApi\Domain\ValueObjects\Money;

/**
 * Product entity demonstrating basic DDD entity pattern.
 */
final class Product extends Entity
{
    public function __construct(
        string $id,
        private string $name,
        private string $description,
        private Money $price,
        private int $stock,
    ) {
        parent::__construct($id);
    }

    /**
     * Factory method to create a new product.
     */
    public static function create(
        string $name,
        string $description,
        Money $price,
        int $stock = 0,
    ): self {
        return new self(
            self::generateId(),
            $name,
            $description,
            $price,
            $stock,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function updateDetails(string $name, string $description): void
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function updatePrice(Money $price): void
    {
        $this->price = $price;
    }

    public function adjustStock(int $quantity): void
    {
        $newStock = $this->stock + $quantity;
        
        if ($newStock < 0) {
            throw new \InvalidArgumentException('Stock cannot be negative');
        }
        
        $this->stock = $newStock;
    }

    /**
     * Check if product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }
}
