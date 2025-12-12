<?php

declare(strict_types=1);

namespace Lumina\DDD\Domain\Repository\Filter;

/**
 * Composite filter that combines two filters with AND logic.
 */
final class AndFilter extends Filter
{
    public function __construct(
        private readonly Filter $left,
        private readonly Filter $right
    ) {
    }

    public function getField(): ?string
    {
        return null;
    }

    public function getType(): string
    {
        return 'AND';
    }

    public function getValue(): mixed
    {
        return null;
    }

    /**
     * Get the left filter.
     */
    public function getLeft(): Filter
    {
        return $this->left;
    }

    /**
     * Get the right filter.
     */
    public function getRight(): Filter
    {
        return $this->right;
    }

    /**
     * Get all filters in this AND combination as a flat array.
     *
     * @return array<int, Filter>
     */
    public function getFilters(): array
    {
        $filters = [];

        if ($this->left instanceof AndFilter) {
            $filters = array_merge($filters, $this->left->getFilters());
        } else {
            $filters[] = $this->left;
        }

        if ($this->right instanceof AndFilter) {
            $filters = array_merge($filters, $this->right->getFilters());
        } else {
            $filters[] = $this->right;
        }

        return $filters;
    }

    public function toArray(): array
    {
        return [
            'type' => 'AND',
            'filters' => [
                $this->left->toArray(),
                $this->right->toArray(),
            ],
        ];
    }
}
