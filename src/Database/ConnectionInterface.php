<?php

declare(strict_types=1);

namespace Luminor\DDD\Database;

use PDO;

/**
 * Database Connection Interface
 *
 * Defines the contract for database connections.
 */
interface ConnectionInterface
{
    /**
     * Get the PDO instance.
     *
     * @return PDO The PDO connection
     */
    public function getPdo(): PDO;

    /**
     * Execute a query and return the statement.
     *
     * @param string $query The SQL query
     * @param array<mixed> $bindings Parameter bindings
     * @return \PDOStatement The executed statement
     */
    public function query(string $query, array $bindings = []): \PDOStatement;

    /**
     * Execute a statement.
     *
     * @param string $query The SQL query
     * @param array<mixed> $bindings Parameter bindings
     * @return int The number of affected rows
     */
    public function statement(string $query, array $bindings = []): int;

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit a transaction.
     */
    public function commit(): void;

    /**
     * Rollback a transaction.
     */
    public function rollback(): void;

    /**
     * Get the database driver name.
     *
     * @return string The driver name (mysql, pgsql, sqlite)
     */
    public function getDriverName(): string;
}
