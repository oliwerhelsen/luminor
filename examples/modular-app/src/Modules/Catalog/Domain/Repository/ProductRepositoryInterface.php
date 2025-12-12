<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Catalog\Domain\Repository;

use Example\ModularApp\Modules\Catalog\Domain\Entities\Product;

/**
 * Product repository interface.
 */
interface ProductRepositoryInterface
{
    public function findById(string $id): ?Product;
    
    /**
     * @return Product[]
     */
    public function findAll(int $offset = 0, int $limit = 50): array;
    
    /**
     * @return Product[]
     */
    public function findByCategory(string $category): array;
    
    public function count(): int;
    
    public function save(Product $product): void;
    
    public function delete(Product $product): void;
}
