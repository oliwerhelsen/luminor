<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders\Domain\Repository;

use Example\ModularApp\Modules\Orders\Domain\Entities\Order;

/**
 * Order repository interface.
 */
interface OrderRepositoryInterface
{
    public function findById(string $id): ?Order;
    
    /**
     * @return Order[]
     */
    public function findByCustomer(string $customerId): array;
    
    /**
     * @return Order[]
     */
    public function findAll(int $offset = 0, int $limit = 50): array;
    
    public function count(): int;
    
    public function save(Order $order): void;
}
