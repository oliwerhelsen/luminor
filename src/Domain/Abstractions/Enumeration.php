<?php

declare(strict_types=1);

namespace Luminor\Domain\Abstractions;

use InvalidArgumentException;
use ReflectionClass;

/**
 * Base class for type-safe enumerations.
 *
 * While PHP 8.1+ has native enums, this class provides additional functionality
 * for enumerations that need to carry a value or require more complex behavior.
 *
 * @template TValue of int|string
 */
abstract class Enumeration
{
    /** @var array<string, array<string, static>> */
    private static array $instances = [];

    /**
     * @param TValue $value The enumeration value
     * @param string $name The enumeration name
     */
    final protected function __construct(
        private readonly int|string $value,
        private readonly string $name
    ) {
    }

    /**
     * Get the enumeration value.
     *
     * @return TValue
     */
    public function getValue(): int|string
    {
        return $this->value;
    }

    /**
     * Get the enumeration name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get a string representation of the enumeration.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Check if this enumeration equals another.
     */
    public function equals(?Enumeration $other): bool
    {
        if ($other === null) {
            return false;
        }

        return $this->value === $other->value && static::class === $other::class;
    }

    /**
     * Get all possible enumeration instances.
     *
     * @return array<string, static>
     */
    public static function getAll(): array
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = self::initializeInstances();
        }

        return self::$instances[$class];
    }

    /**
     * Get all possible enumeration values.
     *
     * @return array<string, int|string>
     */
    public static function getValues(): array
    {
        $values = [];
        foreach (static::getAll() as $name => $instance) {
            $values[$name] = $instance->getValue();
        }
        return $values;
    }

    /**
     * Get all possible enumeration names.
     *
     * @return array<int, string>
     */
    public static function getNames(): array
    {
        return array_keys(static::getAll());
    }

    /**
     * Create an enumeration instance from a value.
     *
     * @param TValue $value
     * @return static
     * @throws InvalidArgumentException If the value is not valid
     */
    public static function from(int|string $value): static
    {
        foreach (static::getAll() as $instance) {
            if ($instance->getValue() === $value) {
                return $instance;
            }
        }

        throw new InvalidArgumentException(
            sprintf('Invalid value "%s" for enumeration %s', $value, static::class)
        );
    }

    /**
     * Try to create an enumeration instance from a value.
     *
     * @param TValue $value
     * @return static|null
     */
    public static function tryFrom(int|string $value): ?static
    {
        try {
            return static::from($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Create an enumeration instance from a name.
     *
     * @throws InvalidArgumentException If the name is not valid
     */
    public static function fromName(string $name): static
    {
        $all = static::getAll();

        if (!isset($all[$name])) {
            throw new InvalidArgumentException(
                sprintf('Invalid name "%s" for enumeration %s', $name, static::class)
            );
        }

        return $all[$name];
    }

    /**
     * Check if a value is valid for this enumeration.
     *
     * @param TValue $value
     */
    public static function isValid(int|string $value): bool
    {
        return static::tryFrom($value) !== null;
    }

    /**
     * Initialize all enumeration instances from class constants.
     *
     * @return array<string, static>
     */
    private static function initializeInstances(): array
    {
        $reflection = new ReflectionClass(static::class);
        $constants = $reflection->getConstants();
        $instances = [];

        foreach ($constants as $name => $value) {
            if (is_int($value) || is_string($value)) {
                $instances[$name] = new static($value, $name);
            }
        }

        return $instances;
    }
}
