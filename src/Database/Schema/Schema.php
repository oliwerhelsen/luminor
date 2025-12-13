<?php

declare(strict_types=1);

namespace Luminor\DDD\Database\Schema;

use Luminor\DDD\Database\ConnectionInterface;
use Closure;

/**
 * Schema Builder
 *
 * Provides a fluent interface for creating and modifying database tables.
 */
final class Schema
{
    private ConnectionInterface $connection;
    private SchemaGrammar $grammar;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->grammar = $this->getGrammar($connection->getDriverName());
    }

    /**
     * Create a new table.
     */
    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $this->grammar->compileCreate($blueprint);
        $this->connection->statement($sql);
    }

    /**
     * Drop a table if it exists.
     */
    public function dropIfExists(string $table): void
    {
        $sql = $this->grammar->compileDropIfExists($table);
        $this->connection->statement($sql);
    }

    /**
     * Check if a table exists.
     */
    public function hasTable(string $table): bool
    {
        $sql = $this->grammar->compileTableExists($table);
        $stmt = $this->connection->query($sql);
        return $stmt->rowCount() > 0;
    }

    /**
     * Rename a table.
     */
    public function rename(string $from, string $to): void
    {
        $sql = $this->grammar->compileRename($from, $to);
        $this->connection->statement($sql);
    }

    /**
     * Get the connection.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the appropriate grammar for the driver.
     */
    private function getGrammar(string $driver): SchemaGrammar
    {
        return match ($driver) {
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlite' => new SqliteGrammar(),
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };
    }
}
