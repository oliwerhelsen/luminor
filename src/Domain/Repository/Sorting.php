<?php

declare(strict_types=1);

namespace Luminor\Domain\Repository;

/**
 * Value object representing sorting configuration.
 */
final class Sorting
{
    public const ASC = 'ASC';
    public const DESC = 'DESC';

    /** @var array<int, array{field: string, direction: string}> */
    private array $orders = [];

    private function __construct()
    {
    }

    /**
     * Create an empty sorting configuration.
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * Create sorting by a single field in ascending order.
     */
    public static function asc(string $field): self
    {
        $sorting = new self();
        $sorting->orders[] = ['field' => $field, 'direction' => self::ASC];
        return $sorting;
    }

    /**
     * Create sorting by a single field in descending order.
     */
    public static function desc(string $field): self
    {
        $sorting = new self();
        $sorting->orders[] = ['field' => $field, 'direction' => self::DESC];
        return $sorting;
    }

    /**
     * Add an ascending sort order.
     */
    public function thenAsc(string $field): self
    {
        $clone = clone $this;
        $clone->orders[] = ['field' => $field, 'direction' => self::ASC];
        return $clone;
    }

    /**
     * Add a descending sort order.
     */
    public function thenDesc(string $field): self
    {
        $clone = clone $this;
        $clone->orders[] = ['field' => $field, 'direction' => self::DESC];
        return $clone;
    }

    /**
     * Get all sort orders.
     *
     * @return array<int, array{field: string, direction: string}>
     */
    public function getOrders(): array
    {
        return $this->orders;
    }

    /**
     * Check if any sorting is defined.
     */
    public function hasOrders(): bool
    {
        return count($this->orders) > 0;
    }

    /**
     * Check if this sorting equals another.
     */
    public function equals(?Sorting $other): bool
    {
        if ($other === null) {
            return false;
        }

        return $this->orders === $other->orders;
    }
}
