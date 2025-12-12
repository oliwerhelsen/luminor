<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Catalog\Infrastructure\Persistence;

use Example\ModularApp\Modules\Catalog\Domain\Entities\Product;
use Example\ModularApp\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;

/**
 * In-memory product repository implementation.
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

    public function findByCategory(string $category): array
    {
        return array_filter(
            $this->products,
            fn(Product $p) => $p->getCategory() === $category
        );
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
