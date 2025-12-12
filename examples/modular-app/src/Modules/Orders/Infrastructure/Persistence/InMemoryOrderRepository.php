<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders\Infrastructure\Persistence;

use Example\ModularApp\Modules\Orders\Domain\Entities\Order;
use Example\ModularApp\Modules\Orders\Domain\Repository\OrderRepositoryInterface;

/**
 * In-memory order repository implementation.
 */
final class InMemoryOrderRepository implements OrderRepositoryInterface
{
    /** @var array<string, Order> */
    private array $orders = [];

    public function findById(string $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function findByCustomer(string $customerId): array
    {
        return array_filter(
            $this->orders,
            fn(Order $o) => $o->getCustomerId() === $customerId
        );
    }

    public function findAll(int $offset = 0, int $limit = 50): array
    {
        return array_slice(array_values($this->orders), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->orders);
    }

    public function save(Order $order): void
    {
        $this->orders[$order->getId()] = $order;
    }
}
