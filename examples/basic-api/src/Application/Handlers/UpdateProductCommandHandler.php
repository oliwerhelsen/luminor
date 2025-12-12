<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Handlers;

use Example\BasicApi\Application\Commands\UpdateProductCommand;
use Example\BasicApi\Domain\Repository\ProductRepositoryInterface;
use Example\BasicApi\Domain\ValueObjects\Money;
use Lumina\DDD\Application\Bus\CommandHandlerInterface;

/**
 * Handler for UpdateProductCommand.
 */
final class UpdateProductCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(UpdateProductCommand $command): bool
    {
        $product = $this->repository->findById($command->id);

        if ($product === null) {
            return false;
        }

        // Update name and description if provided
        if ($command->name !== null || $command->description !== null) {
            $product->updateDetails(
                $command->name ?? $product->getName(),
                $command->description ?? $product->getDescription(),
            );
        }

        // Update price if provided
        if ($command->priceInCents !== null) {
            $currency = $command->currency ?? $product->getPrice()->getCurrency();
            $product->updatePrice(Money::fromCents($command->priceInCents, $currency));
        }

        // Update stock if provided
        if ($command->stock !== null) {
            $difference = $command->stock - $product->getStock();
            $product->adjustStock($difference);
        }

        $this->repository->save($product);

        return true;
    }
}
