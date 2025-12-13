<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders;

use Example\ModularApp\Modules\Orders\Domain\Repository\OrderRepositoryInterface;
use Example\ModularApp\Modules\Orders\Infrastructure\Persistence\InMemoryOrderRepository;
use Luminor\DDD\Container\AbstractServiceProvider;
use Luminor\DDD\Container\ContainerInterface;

/**
 * Orders module service provider.
 */
final class OrdersServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(
            OrderRepositoryInterface::class,
            fn() => new InMemoryOrderRepository()
        );
    }

    public function provides(): array
    {
        return [
            OrderRepositoryInterface::class,
        ];
    }
}
