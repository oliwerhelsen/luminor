<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Inventory;

use Example\ModularApp\Modules\Inventory\Domain\Repository\StockRepositoryInterface;
use Example\ModularApp\Modules\Inventory\Infrastructure\Persistence\InMemoryStockRepository;
use Luminor\Container\AbstractServiceProvider;
use Luminor\Container\ContainerInterface;

/**
 * Inventory module service provider.
 */
final class InventoryServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(
            StockRepositoryInterface::class,
            fn() => new InMemoryStockRepository()
        );
    }

    public function provides(): array
    {
        return [
            StockRepositoryInterface::class,
        ];
    }
}
