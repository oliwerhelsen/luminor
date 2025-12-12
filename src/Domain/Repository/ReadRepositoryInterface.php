<?php

declare(strict_types=1);

namespace Lumina\DDD\Domain\Repository;

use Lumina\DDD\Domain\Abstractions\AggregateRoot;

/**
 * Interface for read-only repository operations.
 *
 * This interface provides query operations without any write capabilities.
 * Useful for implementing CQRS patterns where reads are separated from writes.
 *
 * @template T of AggregateRoot
 */
interface ReadRepositoryInterface
{
    /**
     * Find an aggregate by its identifier.
     *
     * @param mixed $id The unique identifier
     * @return T|null The aggregate or null if not found
     */
    public function findById(mixed $id): ?AggregateRoot;

    /**
     * Find an aggregate by its identifier or throw an exception.
     *
     * @param mixed $id The unique identifier
     * @return T The aggregate
     * @throws AggregateNotFoundException If the aggregate is not found
     */
    public function findByIdOrFail(mixed $id): AggregateRoot;

    /**
     * Find all aggregates.
     *
     * @return array<int, T>
     */
    public function findAll(): array;

    /**
     * Find aggregates matching the given criteria.
     *
     * @return array<int, T>
     */
    public function findByCriteria(Criteria $criteria): array;

    /**
     * Count all aggregates.
     */
    public function count(): int;

    /**
     * Count aggregates matching the given criteria.
     */
    public function countByCriteria(Criteria $criteria): int;

    /**
     * Check if an aggregate with the given identifier exists.
     *
     * @param mixed $id The unique identifier
     */
    public function exists(mixed $id): bool;
}
