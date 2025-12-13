<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Persistence;

/**
 * Interface for database transaction management.
 */
interface TransactionInterface
{
    /**
     * Begin a new transaction.
     */
    public function begin(): void;

    /**
     * Commit the current transaction.
     */
    public function commit(): void;

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void;

    /**
     * Check if a transaction is currently active.
     */
    public function isActive(): bool;

    /**
     * Execute a callback within a transaction.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function transactional(callable $callback): mixed;
}
