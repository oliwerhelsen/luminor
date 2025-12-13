<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Abstractions;

/**
 * Base class for domain entities.
 *
 * An Entity is an object that is distinguished by its identity, rather than its attributes.
 * Two entities with the same identity are considered equal, even if their attributes differ.
 *
 * @template TId of mixed
 */
abstract class Entity
{
    /**
     * @param TId $id The unique identifier for this entity
     */
    public function __construct(
        protected mixed $id,
    ) {
    }

    /**
     * Get the entity's unique identifier.
     *
     * @return TId
     */
    public function getId(): mixed
    {
        return $this->id;
    }

    /**
     * Check if this entity is equal to another entity.
     *
     * Two entities are equal if they have the same type and identity.
     */
    public function equals(?Entity $other): bool
    {
        if ($other === null) {
            return false;
        }

        if ($this === $other) {
            return true;
        }

        if (! $other instanceof static) {
            return false;
        }

        return $this->id === $other->id;
    }

    /**
     * Check if this entity is transient (not yet persisted).
     *
     * An entity is considered transient if it has no identity assigned.
     */
    public function isTransient(): bool
    {
        return $this->id === null;
    }
}
