<?php

declare(strict_types=1);

namespace Luminor\Domain\Repository\Filter;

use InvalidArgumentException;

/**
 * Filter for comparison operations (greater than, less than, etc.).
 */
final class ComparisonFilter extends Filter
{
    public const GREATER_THAN = 'GT';
    public const GREATER_THAN_OR_EQUAL = 'GTE';
    public const LESS_THAN = 'LT';
    public const LESS_THAN_OR_EQUAL = 'LTE';

    private const VALID_OPERATORS = [
        self::GREATER_THAN,
        self::GREATER_THAN_OR_EQUAL,
        self::LESS_THAN,
        self::LESS_THAN_OR_EQUAL,
    ];

    public function __construct(
        private readonly string $field,
        private readonly string $operator,
        private readonly mixed $value
    ) {
        if (!in_array($operator, self::VALID_OPERATORS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid operator "%s". Valid operators: %s', $operator, implode(', ', self::VALID_OPERATORS))
            );
        }
    }

    /**
     * Create a greater than filter.
     */
    public static function greaterThan(string $field, mixed $value): self
    {
        return new self($field, self::GREATER_THAN, $value);
    }

    /**
     * Create a greater than or equal filter.
     */
    public static function greaterThanOrEqual(string $field, mixed $value): self
    {
        return new self($field, self::GREATER_THAN_OR_EQUAL, $value);
    }

    /**
     * Create a less than filter.
     */
    public static function lessThan(string $field, mixed $value): self
    {
        return new self($field, self::LESS_THAN, $value);
    }

    /**
     * Create a less than or equal filter.
     */
    public static function lessThanOrEqual(string $field, mixed $value): self
    {
        return new self($field, self::LESS_THAN_OR_EQUAL, $value);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getType(): string
    {
        return $this->operator;
    }

    /**
     * Get the comparison operator.
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->operator,
            'field' => $this->field,
            'value' => $this->value,
        ];
    }
}
