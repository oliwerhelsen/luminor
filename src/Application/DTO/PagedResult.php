<?php

declare(strict_types=1);

namespace Lumina\DDD\Application\DTO;

/**
 * Represents a paginated result set.
 *
 * @template T
 */
final class PagedResult
{
    /**
     * @param array<int, T> $items The items in the current page
     * @param int $totalCount The total number of items across all pages
     * @param int $page The current page number (1-indexed)
     * @param int $perPage The number of items per page
     */
    public function __construct(
        private readonly array $items,
        private readonly int $totalCount,
        private readonly int $page,
        private readonly int $perPage
    ) {
    }

    /**
     * Create an empty paged result.
     *
     * @return self<T>
     */
    public static function empty(int $page = 1, int $perPage = 25): self
    {
        return new self([], 0, $page, $perPage);
    }

    /**
     * Create a paged result from an array of items.
     *
     * @param array<int, T> $items
     * @return self<T>
     */
    public static function fromItems(array $items, int $totalCount, int $page = 1, int $perPage = 25): self
    {
        return new self($items, $totalCount, $page, $perPage);
    }

    /**
     * Get the items in the current page.
     *
     * @return array<int, T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get the total count of items across all pages.
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Get the current page number.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Get the number of items per page.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the total number of pages.
     */
    public function getTotalPages(): int
    {
        if ($this->perPage <= 0) {
            return 0;
        }

        return (int) ceil($this->totalCount / $this->perPage);
    }

    /**
     * Check if there is a next page.
     */
    public function hasNextPage(): bool
    {
        return $this->page < $this->getTotalPages();
    }

    /**
     * Check if there is a previous page.
     */
    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    /**
     * Check if the result is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * Get the number of items in the current page.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get the starting item number for the current page.
     */
    public function getFrom(): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        return ($this->page - 1) * $this->perPage + 1;
    }

    /**
     * Get the ending item number for the current page.
     */
    public function getTo(): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        return $this->getFrom() + $this->count() - 1;
    }

    /**
     * Map the items using a callback function.
     *
     * @template TNew
     * @param callable(T): TNew $callback
     * @return self<TNew>
     */
    public function map(callable $callback): self
    {
        return new self(
            array_map($callback, $this->items),
            $this->totalCount,
            $this->page,
            $this->perPage
        );
    }

    /**
     * Convert the paged result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn($item) => $item instanceof DataTransferObject ? $item->toArray() : $item,
                $this->items
            ),
            'pagination' => [
                'page' => $this->page,
                'perPage' => $this->perPage,
                'totalCount' => $this->totalCount,
                'totalPages' => $this->getTotalPages(),
                'hasNextPage' => $this->hasNextPage(),
                'hasPreviousPage' => $this->hasPreviousPage(),
                'from' => $this->getFrom(),
                'to' => $this->getTo(),
            ],
        ];
    }
}
