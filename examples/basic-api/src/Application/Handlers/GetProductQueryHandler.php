<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Handlers;

use Example\BasicApi\Application\DTOs\ProductDto;
use Example\BasicApi\Application\Queries\GetProductQuery;
use Example\BasicApi\Domain\Repository\ProductRepositoryInterface;
use Luminor\Application\Bus\QueryHandlerInterface;

/**
 * Handler for GetProductQuery.
 */
final class GetProductQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetProductQuery $query): ?ProductDto
    {
        $product = $this->repository->findById($query->id);

        if ($product === null) {
            return null;
        }

        return ProductDto::fromEntity($product);
    }
}
