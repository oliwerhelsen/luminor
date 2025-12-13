<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Catalog;

use Example\ModularApp\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;
use Example\ModularApp\Modules\Catalog\Infrastructure\Persistence\InMemoryProductRepository;
use Luminor\DDD\Container\AbstractServiceProvider;
use Luminor\DDD\Container\ContainerInterface;

/**
 * Catalog module service provider.
 */
final class CatalogServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register repository
        $container->singleton(
            ProductRepositoryInterface::class,
            fn() => new InMemoryProductRepository()
        );
    }

    public function provides(): array
    {
        return [
            ProductRepositoryInterface::class,
        ];
    }
}
