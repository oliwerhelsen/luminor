<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Handlers;

use Example\BasicApi\Application\Commands\DeleteProductCommand;
use Example\BasicApi\Domain\Repository\ProductRepositoryInterface;
use Lumina\DDD\Application\Bus\CommandHandlerInterface;

/**
 * Handler for DeleteProductCommand.
 */
final class DeleteProductCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(DeleteProductCommand $command): bool
    {
        $product = $this->repository->findById($command->id);

        if ($product === null) {
            return false;
        }

        $this->repository->delete($product);

        return true;
    }
}
