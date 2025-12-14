<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Persistence;

use Luminor\Domain\Abstractions\AggregateRoot;

/**
 * Unit of Work interface for managing persistence operations.
 *
 * The Unit of Work pattern maintains a list of objects affected by a
 * business transaction and coordinates the writing out of changes.
 */
interface UnitOfWorkInterface
{
    /**
     * Mark an aggregate as new (to be inserted).
     */
    public function registerNew(AggregateRoot $aggregate): void;

    /**
     * Mark an aggregate as dirty (to be updated).
     */
    public function registerDirty(AggregateRoot $aggregate): void;

    /**
     * Mark an aggregate as removed (to be deleted).
     */
    public function registerRemoved(AggregateRoot $aggregate): void;

    /**
     * Mark an aggregate as clean (no pending changes).
     */
    public function registerClean(AggregateRoot $aggregate): void;

    /**
     * Commit all pending changes to the database.
     *
     * This persists all registered new, dirty, and removed aggregates.
     */
    public function commit(): void;

    /**
     * Rollback all pending changes.
     *
     * This clears all registered aggregates without persisting.
     */
    public function rollback(): void;

    /**
     * Clear all registered aggregates without committing.
     */
    public function clear(): void;

    /**
     * Check if an aggregate is registered for any operation.
     */
    public function isRegistered(AggregateRoot $aggregate): bool;

    /**
     * Get the count of pending operations.
     */
    public function getPendingCount(): int;
}
