<?php

declare(strict_types=1);

namespace Luminor\Domain\Abstractions;

/**
 * Base class for value objects.
 *
 * A Value Object is an object that describes some characteristic or attribute but carries
 * no concept of identity. Two value objects are equal if all their properties are equal.
 *
 * Value objects should be immutable - once created, they cannot be changed.
 */
abstract class ValueObject
{
    /**
     * Check if this value object is equal to another.
     *
     * Two value objects are equal if they are of the same type and all their
     * properties have the same values.
     */
    public function equals(?ValueObject $other): bool
    {
        if ($other === null) {
            return false;
        }

        if ($this === $other) {
            return true;
        }

        if (!$other instanceof static) {
            return false;
        }

        return $this->getEqualityComponents() === $other->getEqualityComponents();
    }

    /**
     * Get the components used for equality comparison.
     *
     * Override this method to specify which properties should be used
     * when comparing two value objects for equality.
     *
     * @return array<int, mixed>
     */
    abstract protected function getEqualityComponents(): array;

    /**
     * Convert the value object to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties();
        $result = [];

        foreach ($properties as $property) {
            $value = $property->getValue($this);

            if ($value instanceof ValueObject) {
                $value = $value->toArray();
            }

            $result[$property->getName()] = $value;
        }

        return $result;
    }

    /**
     * Get a string representation of the value object.
     */
    public function __toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
