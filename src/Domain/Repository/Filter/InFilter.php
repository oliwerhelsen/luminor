<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository\Filter;

/**
 * Filter that matches when a field value is in a list of values.
 */
final class InFilter extends Filter
{
    /**
     * @param array<int, mixed> $values
     */
    public function __construct(
        private readonly string $field,
        private readonly array $values,
    ) {
    }

    /**
     * Create an IN filter.
     *
     * @param array<int, mixed> $values
     */
    public static function create(string $field, array $values): self
    {
        return new self($field, $values);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getType(): string
    {
        return 'IN';
    }

    /**
     * @return array<int, mixed>
     */
    public function getValue(): array
    {
        return $this->values;
    }

    public function toArray(): array
    {
        return [
            'type' => 'IN',
            'field' => $this->field,
            'values' => $this->values,
        ];
    }
}
