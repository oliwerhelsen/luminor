<?php

declare(strict_types=1);

namespace Luminor\DDD\Testing\Factory;

use Luminor\DDD\Domain\Abstractions\Entity;

/**
 * Factory for creating test entities.
 *
 * Provides a fluent interface for building entities with
 * customizable attributes for testing.
 *
 * @template T of Entity
 */
abstract class EntityFactory
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $defaultAttributes = [];

    /** @var int */
    private static int $sequence = 0;

    /**
     * Create a new factory instance.
     *
     * @return static
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Get the entity class this factory creates.
     *
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    /**
     * Get default attribute values.
     *
     * @return array<string, mixed>
     */
    abstract protected function definition(): array;

    /**
     * Create the entity instance from attributes.
     *
     * @param array<string, mixed> $attributes
     * @return T
     */
    abstract protected function make(array $attributes): Entity;

    public function __construct()
    {
        $this->defaultAttributes = $this->definition();
        $this->attributes = [];
    }

    /**
     * Set a single attribute.
     *
     * @return static
     */
    public function with(string $key, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    /**
     * Set multiple attributes.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function withAttributes(array $attributes): static
    {
        $clone = clone $this;
        $clone->attributes = array_merge($clone->attributes, $attributes);
        return $clone;
    }

    /**
     * Set the ID attribute.
     *
     * @return static
     */
    public function withId(string $id): static
    {
        return $this->with('id', $id);
    }

    /**
     * Create a single entity.
     *
     * @return T
     */
    public function create(): Entity
    {
        $attributes = array_merge($this->defaultAttributes, $this->attributes);
        return $this->make($attributes);
    }

    /**
     * Create multiple entities.
     *
     * @param int $count
     * @return array<T>
     */
    public function createMany(int $count): array
    {
        $entities = [];
        for ($i = 0; $i < $count; $i++) {
            $entities[] = $this->create();
        }
        return $entities;
    }

    /**
     * Create entities using a state modifier.
     *
     * @param callable(static): static $callback
     * @return static
     */
    public function state(callable $callback): static
    {
        return $callback($this);
    }

    /**
     * Get a sequential value for unique attributes.
     */
    protected static function sequence(): int
    {
        return ++self::$sequence;
    }

    /**
     * Reset the sequence counter.
     */
    public static function resetSequence(): void
    {
        self::$sequence = 0;
    }

    /**
     * Generate a unique ID.
     */
    protected function generateId(): string
    {
        return sprintf('%s-%d-%s', strtolower(class_basename($this->getEntityClass())), self::sequence(), bin2hex(random_bytes(4)));
    }

    /**
     * Generate a fake email.
     */
    protected function fakeEmail(string $prefix = 'user'): string
    {
        return sprintf('%s%d@example.com', $prefix, self::sequence());
    }

    /**
     * Generate a fake name.
     */
    protected function fakeName(): string
    {
        $names = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Diana', 'Eve', 'Frank'];
        $surnames = ['Doe', 'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller'];

        return $names[array_rand($names)] . ' ' . $surnames[array_rand($surnames)];
    }

    /**
     * Generate a random string.
     */
    protected function randomString(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', $length)), 0, $length);
    }
}

/**
 * Get the class basename.
 */
function class_basename(string $class): string
{
    $parts = explode('\\', $class);
    return end($parts);
}
