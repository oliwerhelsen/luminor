<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Handlers;

use Example\BasicApi\Application\DTOs\ProductDto;
use Example\BasicApi\Application\Queries\ListProductsQuery;
use Example\BasicApi\Domain\Repository\ProductRepositoryInterface;
use Lumina\DDD\Application\Bus\QueryHandlerInterface;
use Lumina\DDD\Application\DTO\PagedResult;

/**
 * Handler for ListProductsQuery.
 */
final class ListProductsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(ListProductsQuery $query): PagedResult
    {
        $offset = ($query->page - 1) * $query->perPage;
        $products = $this->repository->findAll($offset, $query->perPage);
        $total = $this->repository->count();

        $items = array_map(
            fn($product) => ProductDto::fromEntity($product),
            $products
        );

        return new PagedResult(
            items: $items,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
        );
    }
}
