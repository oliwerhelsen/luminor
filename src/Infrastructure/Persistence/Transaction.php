<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Persistence;

/**
 * Transaction wrapper for database operations.
 *
 * Provides a simple interface for managing database transactions
 * with support for nested transactions via savepoints.
 */
final class Transaction implements TransactionInterface
{
    private int $transactionLevel = 0;

    /**
     * @param callable(): void $beginCallback Called when beginning a transaction
     * @param callable(): void $commitCallback Called when committing a transaction
     * @param callable(): void $rollbackCallback Called when rolling back a transaction
     * @param callable(string): void|null $savepointCallback Called for savepoints (nested transactions)
     * @param callable(string): void|null $releaseSavepointCallback Called to release savepoints
     * @param callable(string): void|null $rollbackToSavepointCallback Called to rollback to savepoint
     */
    public function __construct(
        private readonly mixed $beginCallback,
        private readonly mixed $commitCallback,
        private readonly mixed $rollbackCallback,
        private readonly mixed $savepointCallback = null,
        private readonly mixed $releaseSavepointCallback = null,
        private readonly mixed $rollbackToSavepointCallback = null
    ) {
    }

    /**
     * Create a transaction wrapper from PDO.
     */
    public static function fromPdo(\PDO $pdo): self
    {
        return new self(
            fn() => $pdo->beginTransaction(),
            fn() => $pdo->commit(),
            fn() => $pdo->rollBack(),
            fn(string $name) => $pdo->exec("SAVEPOINT {$name}"),
            fn(string $name) => $pdo->exec("RELEASE SAVEPOINT {$name}"),
            fn(string $name) => $pdo->exec("ROLLBACK TO SAVEPOINT {$name}")
        );
    }

    /**
     * @inheritDoc
     */
    public function begin(): void
    {
        if ($this->transactionLevel === 0) {
            ($this->beginCallback)();
        } elseif ($this->savepointCallback !== null) {
            ($this->savepointCallback)($this->getSavepointName());
        }

        $this->transactionLevel++;
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            throw new \RuntimeException('No active transaction to commit');
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            ($this->commitCallback)();
        } elseif ($this->releaseSavepointCallback !== null) {
            ($this->releaseSavepointCallback)($this->getSavepointName());
        }
    }

    /**
     * @inheritDoc
     */
    public function rollback(): void
    {
        if ($this->transactionLevel === 0) {
            throw new \RuntimeException('No active transaction to rollback');
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            ($this->rollbackCallback)();
        } elseif ($this->rollbackToSavepointCallback !== null) {
            ($this->rollbackToSavepointCallback)($this->getSavepointName());
        }
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * @inheritDoc
     */
    public function transactional(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get the current transaction nesting level.
     */
    public function getTransactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Get the savepoint name for the current level.
     */
    private function getSavepointName(): string
    {
        return "savepoint_{$this->transactionLevel}";
    }
}
