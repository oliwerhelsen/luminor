<?php

declare(strict_types=1);

namespace Example\BasicApi\Infrastructure\Persistence;

use Example\BasicApi\Domain\Entities\Product;
use Example\BasicApi\Domain\Repository\ProductRepositoryInterface;

/**
 * In-memory implementation of ProductRepository for demonstration.
 */
final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<string, Product> */
    private array $products = [];

    public function findById(string $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findAll(int $offset = 0, int $limit = 50): array
    {
        return array_slice(array_values($this->products), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->products);
    }

    public function save(Product $product): void
    {
        $this->products[$product->getId()] = $product;
    }

    public function delete(Product $product): void
    {
        unset($this->products[$product->getId()]);
    }
}
