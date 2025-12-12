<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Inventory\Infrastructure\Persistence;

use Example\ModularApp\Modules\Inventory\Domain\Entities\Stock;
use Example\ModularApp\Modules\Inventory\Domain\Repository\StockRepositoryInterface;

/**
 * In-memory stock repository implementation.
 */
final class InMemoryStockRepository implements StockRepositoryInterface
{
    /** @var array<string, Stock> */
    private array $stocks = [];
    
    /** @var array<string, string> productId => stockId mapping */
    private array $productIndex = [];

    public function findById(string $id): ?Stock
    {
        return $this->stocks[$id] ?? null;
    }

    public function findByProductId(string $productId): ?Stock
    {
        if (!isset($this->productIndex[$productId])) {
            return null;
        }
        
        return $this->stocks[$this->productIndex[$productId]] ?? null;
    }

    public function save(Stock $stock): void
    {
        $this->stocks[$stock->getId()] = $stock;
        $this->productIndex[$stock->getProductId()] = $stock->getId();
    }

    public function delete(Stock $stock): void
    {
        unset($this->productIndex[$stock->getProductId()]);
        unset($this->stocks[$stock->getId()]);
    }
}
