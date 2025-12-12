<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Catalog\Domain\Entities;

use Lumina\DDD\Domain\Abstractions\AggregateRoot;
use Example\ModularApp\Modules\Catalog\Domain\Events\ProductCreatedEvent;
use Example\ModularApp\Modules\Catalog\Domain\ValueObjects\ProductId;
use Example\ModularApp\Modules\Catalog\Domain\ValueObjects\Money;

/**
 * Product aggregate root.
 */
final class Product extends AggregateRoot
{
    private function __construct(
        string $id,
        private string $name,
        private string $description,
        private Money $price,
        private string $category,
        private bool $active,
    ) {
        parent::__construct($id);
    }

    /**
     * Create a new product.
     */
    public static function create(
        string $name,
        string $description,
        Money $price,
        string $category,
    ): self {
        $id = self::generateId();
        $product = new self($id, $name, $description, $price, $category, true);
        
        $product->recordEvent(new ProductCreatedEvent(
            $id,
            $name,
            $price->getAmount(),
            $price->getCurrency()
        ));
        
        return $product;
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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function isActive(): bool
    {
        return $this->active;
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

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
