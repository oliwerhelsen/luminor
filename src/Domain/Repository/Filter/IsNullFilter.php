<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository\Filter;

/**
 * Filter that matches when a field is null or not null.
 */
final class IsNullFilter extends Filter
{
    public function __construct(
        private readonly string $field,
        private readonly bool $isNull = true
    ) {
    }

    /**
     * Create a filter for null values.
     */
    public static function null(string $field): self
    {
        return new self($field, true);
    }

    /**
     * Create a filter for non-null values.
     */
    public static function notNull(string $field): self
    {
        return new self($field, false);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getType(): string
    {
        return $this->isNull ? 'IS_NULL' : 'IS_NOT_NULL';
    }

    public function getValue(): bool
    {
        return $this->isNull;
    }

    /**
     * Check if this filter matches null values.
     */
    public function isNull(): bool
    {
        return $this->isNull;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'field' => $this->field,
        ];
    }
}
