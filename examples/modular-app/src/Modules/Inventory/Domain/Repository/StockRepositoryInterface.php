<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Inventory\Domain\Repository;

use Example\ModularApp\Modules\Inventory\Domain\Entities\Stock;

/**
 * Stock repository interface.
 */
interface StockRepositoryInterface
{
    public function findById(string $id): ?Stock;
    
    public function findByProductId(string $productId): ?Stock;
    
    public function save(Stock $stock): void;
    
    public function delete(Stock $stock): void;
}
