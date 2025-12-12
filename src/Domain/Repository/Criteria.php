<?php

declare(strict_types=1);

namespace Lumina\DDD\Domain\Repository;

use Lumina\DDD\Domain\Repository\Filter\Filter;

/**
 * Criteria for querying repositories.
 *
 * Combines filters, sorting, and pagination into a single query specification.
 */
final class Criteria
{
    private function __construct(
        private readonly ?Filter $filter,
        private readonly Sorting $sorting,
        private readonly ?Pagination $pagination
    ) {
    }

    /**
     * Create empty criteria with no filters, sorting, or pagination.
     */
    public static function create(): self
    {
        return new self(null, Sorting::none(), null);
    }

    /**
     * Create criteria with a filter.
     */
    public static function withFilter(Filter $filter): self
    {
        return new self($filter, Sorting::none(), null);
    }

    /**
     * Create criteria with sorting.
     */
    public static function withSorting(Sorting $sorting): self
    {
        return new self(null, $sorting, null);
    }

    /**
     * Create criteria with pagination.
     */
    public static function withPagination(Pagination $pagination): self
    {
        return new self(null, Sorting::none(), $pagination);
    }

    /**
     * Add a filter to the criteria.
     */
    public function filter(Filter $filter): self
    {
        if ($this->filter !== null) {
            return new self($this->filter->and($filter), $this->sorting, $this->pagination);
        }

        return new self($filter, $this->sorting, $this->pagination);
    }

    /**
     * Add an OR filter to the criteria.
     */
    public function orFilter(Filter $filter): self
    {
        if ($this->filter !== null) {
            return new self($this->filter->or($filter), $this->sorting, $this->pagination);
        }

        return new self($filter, $this->sorting, $this->pagination);
    }

    /**
     * Set the sorting.
     */
    public function sortBy(Sorting $sorting): self
    {
        return new self($this->filter, $sorting, $this->pagination);
    }

    /**
     * Add ascending sort order.
     */
    public function orderByAsc(string $field): self
    {
        $sorting = $this->sorting->hasOrders()
            ? $this->sorting->thenAsc($field)
            : Sorting::asc($field);

        return new self($this->filter, $sorting, $this->pagination);
    }

    /**
     * Add descending sort order.
     */
    public function orderByDesc(string $field): self
    {
        $sorting = $this->sorting->hasOrders()
            ? $this->sorting->thenDesc($field)
            : Sorting::desc($field);

        return new self($this->filter, $sorting, $this->pagination);
    }

    /**
     * Set the pagination.
     */
    public function paginate(Pagination $pagination): self
    {
        return new self($this->filter, $this->sorting, $pagination);
    }

    /**
     * Set pagination by page number.
     */
    public function page(int $page, int $perPage = Pagination::DEFAULT_PER_PAGE): self
    {
        return new self($this->filter, $this->sorting, Pagination::create($page, $perPage));
    }

    /**
     * Set pagination by offset and limit.
     */
    public function limit(int $limit, int $offset = 0): self
    {
        return new self($this->filter, $this->sorting, Pagination::fromOffsetLimit($offset, $limit));
    }

    /**
     * Get the filter.
     */
    public function getFilter(): ?Filter
    {
        return $this->filter;
    }

    /**
     * Get the sorting.
     */
    public function getSorting(): Sorting
    {
        return $this->sorting;
    }

    /**
     * Get the pagination.
     */
    public function getPagination(): ?Pagination
    {
        return $this->pagination;
    }

    /**
     * Check if criteria has any filter.
     */
    public function hasFilter(): bool
    {
        return $this->filter !== null;
    }

    /**
     * Check if criteria has any sorting.
     */
    public function hasSorting(): bool
    {
        return $this->sorting->hasOrders();
    }

    /**
     * Check if criteria has pagination.
     */
    public function hasPagination(): bool
    {
        return $this->pagination !== null;
    }

    /**
     * Convert the criteria to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter?->toArray(),
            'sorting' => $this->sorting->getOrders(),
            'pagination' => $this->pagination !== null ? [
                'page' => $this->pagination->getPage(),
                'perPage' => $this->pagination->getPerPage(),
            ] : null,
        ];
    }
}
