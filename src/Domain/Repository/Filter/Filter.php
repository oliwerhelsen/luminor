<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository\Filter;

/**
 * Base class for repository filters.
 *
 * Filters represent conditions that can be applied to repository queries.
 * They can be combined using AND/OR logic to create complex query conditions.
 */
abstract class Filter
{
    /**
     * Get the field name this filter applies to.
     * Returns null for composite filters (AND/OR).
     */
    abstract public function getField(): ?string;

    /**
     * Get the filter type identifier.
     */
    abstract public function getType(): string;

    /**
     * Get the filter value.
     * Returns null for composite filters.
     */
    abstract public function getValue(): mixed;

    /**
     * Create a new filter that is satisfied when both this filter
     * and the other filter are satisfied (AND).
     */
    public function and(Filter $other): AndFilter
    {
        return new AndFilter($this, $other);
    }

    /**
     * Create a new filter that is satisfied when either this filter
     * or the other filter is satisfied (OR).
     */
    public function or(Filter $other): OrFilter
    {
        return new OrFilter($this, $other);
    }

    /**
     * Check if this filter is a composite filter (AND/OR).
     */
    public function isComposite(): bool
    {
        return $this instanceof AndFilter || $this instanceof OrFilter;
    }

    /**
     * Convert the filter to an array representation.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
