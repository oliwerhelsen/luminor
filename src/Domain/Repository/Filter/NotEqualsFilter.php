<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository\Filter;

/**
 * Filter that matches when a field does not equal a specific value.
 */
final class NotEqualsFilter extends Filter
{
    public function __construct(
        private readonly string $field,
        private readonly mixed $value,
    ) {
    }

    /**
     * Create a not equals filter.
     */
    public static function create(string $field, mixed $value): self
    {
        return new self($field, $value);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getType(): string
    {
        return 'NOT_EQUALS';
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function toArray(): array
    {
        return [
            'type' => 'NOT_EQUALS',
            'field' => $this->field,
            'value' => $this->value,
        ];
    }
}
