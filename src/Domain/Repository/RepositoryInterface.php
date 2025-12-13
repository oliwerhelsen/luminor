<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository;

use Luminor\DDD\Domain\Abstractions\AggregateRoot;

/**
 * Interface for repositories that handle aggregate roots.
 *
 * Repositories provide a collection-like interface for accessing domain objects.
 * They encapsulate the logic required to access data sources and provide
 * a more object-oriented view of the persistence layer.
 *
 * @template T of AggregateRoot
 */
interface RepositoryInterface extends ReadRepositoryInterface
{
    /**
     * Add a new aggregate to the repository.
     *
     * @param T $aggregate
     */
    public function add(AggregateRoot $aggregate): void;

    /**
     * Update an existing aggregate in the repository.
     *
     * @param T $aggregate
     */
    public function update(AggregateRoot $aggregate): void;

    /**
     * Remove an aggregate from the repository.
     *
     * @param T $aggregate
     */
    public function remove(AggregateRoot $aggregate): void;

    /**
     * Remove an aggregate by its identifier.
     *
     * @param mixed $id
     */
    public function removeById(mixed $id): void;
}
