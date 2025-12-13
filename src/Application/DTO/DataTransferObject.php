<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\DTO;

use ReflectionClass;
use ReflectionProperty;

/**
 * Base class for Data Transfer Objects.
 *
 * DTOs are simple objects that carry data between processes.
 * They should be immutable and contain no business logic.
 */
abstract class DataTransferObject
{
    /**
     * Create a DTO from an array of data.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $instance = $reflection->newInstanceWithoutConstructor();
            foreach ($data as $key => $value) {
                if ($reflection->hasProperty($key)) {
                    $property = $reflection->getProperty($key);
                    $property->setValue($instance, $value);
                }
            }
            return $instance;
        }

        $parameters = $constructor->getParameters();
        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $data)) {
                $args[] = $data[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $args[] = null;
            } else {
                throw new \InvalidArgumentException(
                    sprintf('Missing required parameter "%s" for %s', $name, static::class)
                );
            }
        }

        return new static(...$args);
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $value = $property->getValue($this);

            if ($value instanceof DataTransferObject) {
                $value = $value->toArray();
            } elseif (is_array($value)) {
                $value = array_map(
                    fn($item) => $item instanceof DataTransferObject ? $item->toArray() : $item,
                    $value
                );
            }

            $result[$property->getName()] = $value;
        }

        return $result;
    }

    /**
     * Create a new instance with modified values.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public function with(array $data): static
    {
        return static::fromArray(array_merge($this->toArray(), $data));
    }

    /**
     * Check if this DTO equals another.
     */
    public function equals(?DataTransferObject $other): bool
    {
        if ($other === null) {
            return false;
        }

        if (!$other instanceof static) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    /**
     * Get a JSON representation of the DTO.
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Create a DTO from a JSON string.
     *
     * @return static
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return static::fromArray($data);
    }
}
