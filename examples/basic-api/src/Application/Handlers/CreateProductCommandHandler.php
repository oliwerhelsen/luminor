<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Handlers;

use Example\BasicApi\Application\Commands\CreateProductCommand;
use Example\BasicApi\Domain\Entities\Product;
use Example\BasicApi\Domain\Repository\ProductRepositoryInterface;
use Example\BasicApi\Domain\ValueObjects\Money;
use Lumina\DDD\Application\Bus\CommandHandlerInterface;

/**
 * Handler for CreateProductCommand.
 */
final class CreateProductCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(CreateProductCommand $command): string
    {
        $product = Product::create(
            name: $command->name,
            description: $command->description,
            price: Money::fromCents($command->priceInCents, $command->currency),
            stock: $command->stock,
        );

        $this->repository->save($product);

        return $product->getId();
    }
}
