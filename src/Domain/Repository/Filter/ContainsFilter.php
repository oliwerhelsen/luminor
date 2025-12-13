<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository\Filter;

/**
 * Filter that matches when a field contains a specific substring.
 */
final class ContainsFilter extends Filter
{
    public function __construct(
        private readonly string $field,
        private readonly string $value,
        private readonly bool $caseSensitive = false,
    ) {
    }

    /**
     * Create a contains filter.
     */
    public static function create(string $field, string $value, bool $caseSensitive = false): self
    {
        return new self($field, $value, $caseSensitive);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getType(): string
    {
        return 'CONTAINS';
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Check if the filter is case sensitive.
     */
    public function isCaseSensitive(): bool
    {
        return $this->caseSensitive;
    }

    public function toArray(): array
    {
        return [
            'type' => 'CONTAINS',
            'field' => $this->field,
            'value' => $this->value,
            'caseSensitive' => $this->caseSensitive,
        ];
    }
}
