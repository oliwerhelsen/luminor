<?php

declare(strict_types=1);

namespace Example\BasicApi\Domain\Repository;

use Example\BasicApi\Domain\Entities\Product;

/**
 * Product repository interface - domain contract for persistence.
 */
interface ProductRepositoryInterface
{
    /**
     * Find a product by its ID.
     */
    public function findById(string $id): ?Product;

    /**
     * Find all products with optional pagination.
     *
     * @return Product[]
     */
    public function findAll(int $offset = 0, int $limit = 50): array;

    /**
     * Count total products.
     */
    public function count(): int;

    /**
     * Save a product (create or update).
     */
    public function save(Product $product): void;

    /**
     * Delete a product.
     */
    public function delete(Product $product): void;
}
