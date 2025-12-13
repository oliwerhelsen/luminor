<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Inventory\Domain\Entities;

use Luminor\DDD\Domain\Abstractions\Entity;
use Example\ModularApp\Modules\Inventory\Domain\Events\StockAdjustedEvent;
use Example\ModularApp\Modules\Inventory\Domain\Events\StockReservedEvent;

/**
 * Stock entity representing inventory for a product.
 */
final class Stock extends Entity
{
    /** @var array<string, int> Reserved quantities by reservation ID */
    private array $reservations = [];

    private function __construct(
        string $id,
        private readonly string $productId,
        private int $quantity,
        private int $reservedQuantity = 0,
    ) {
        parent::__construct($id);
    }

    /**
     * Create stock record for a product.
     */
    public static function create(string $productId, int $initialQuantity = 0): self
    {
        return new self(self::generateId(), $productId, $initialQuantity);
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->quantity - $this->reservedQuantity;
    }

    /**
     * Adjust stock quantity.
     */
    public function adjust(int $adjustment, string $reason = ''): void
    {
        $newQuantity = $this->quantity + $adjustment;
        
        if ($newQuantity < 0) {
            throw new \InvalidArgumentException('Stock cannot be negative');
        }
        
        if ($newQuantity < $this->reservedQuantity) {
            throw new \InvalidArgumentException('Cannot reduce stock below reserved quantity');
        }
        
        $this->quantity = $newQuantity;
    }

    /**
     * Reserve stock for an order.
     */
    public function reserve(int $quantity, string $reservationId): void
    {
        if ($quantity > $this->getAvailableQuantity()) {
            throw new \InvalidArgumentException('Insufficient available stock');
        }
        
        $this->reservations[$reservationId] = $quantity;
        $this->reservedQuantity += $quantity;
    }

    /**
     * Release a reservation.
     */
    public function releaseReservation(string $reservationId): void
    {
        if (!isset($this->reservations[$reservationId])) {
            return;
        }
        
        $this->reservedQuantity -= $this->reservations[$reservationId];
        unset($this->reservations[$reservationId]);
    }

    /**
     * Commit a reservation (deduct from actual stock).
     */
    public function commitReservation(string $reservationId): void
    {
        if (!isset($this->reservations[$reservationId])) {
            throw new \InvalidArgumentException('Reservation not found');
        }
        
        $quantity = $this->reservations[$reservationId];
        $this->quantity -= $quantity;
        $this->reservedQuantity -= $quantity;
        unset($this->reservations[$reservationId]);
    }

    /**
     * Check if requested quantity is available.
     */
    public function isAvailable(int $quantity): bool
    {
        return $this->getAvailableQuantity() >= $quantity;
    }
}
