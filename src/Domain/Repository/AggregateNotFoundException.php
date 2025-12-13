<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository;

use Luminor\DDD\Domain\Abstractions\DomainException;

/**
 * Exception thrown when an aggregate is not found in the repository.
 */
final class AggregateNotFoundException extends DomainException
{
    /**
     * Create an exception for a missing aggregate.
     *
     * @param string $aggregateType The type of aggregate that was not found
     * @param mixed $id The identifier that was searched for
     */
    public static function withId(string $aggregateType, mixed $id): self
    {
        return new self(
            sprintf('%s with ID "%s" was not found', $aggregateType, (string) $id),
            'AGGREGATE_NOT_FOUND',
            [
                'aggregateType' => $aggregateType,
                'id' => $id,
            ]
        );
    }

    /**
     * Create an exception for a missing aggregate.
     * Alias for withId()
     *
     * @param string $aggregateType The type of aggregate that was not found
     * @param mixed $id The identifier that was searched for
     */
    public static function forId(string $aggregateType, mixed $id): self
    {
        return self::withId($aggregateType, $id);
    }
}
