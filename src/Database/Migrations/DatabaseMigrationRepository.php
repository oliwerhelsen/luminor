<?php

declare(strict_types=1);

namespace Luminor\DDD\Database\Migrations;

use Luminor\DDD\Database\ConnectionInterface;
use PDO;

/**
 * Database Migration Repository
 *
 * Stores migration history in a database table.
 */
final class DatabaseMigrationRepository implements MigrationRepositoryInterface
{
    private ConnectionInterface $connection;
    private string $table;

    public function __construct(
        ConnectionInterface $connection,
        string $table = 'migrations'
    ) {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * @inheritDoc
     */
    public function getMigrations(): array
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->query(
            "SELECT migration FROM {$this->table} ORDER BY id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function log(string $name, int $batch): void
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)"
        );

        $stmt->execute([$name, $batch]);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "DELETE FROM {$this->table} WHERE migration = ?"
        );

        $stmt->execute([$name]);
    }

    /**
     * @inheritDoc
     */
    public function getLastBatchNumber(): int
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->query(
            "SELECT MAX(batch) FROM {$this->table}"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function getMigrationsByBatch(int $batch): array
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY id DESC"
        );

        $stmt->execute([$batch]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function createRepository(): void
    {
        $pdo = $this->connection->getPdo();

        $sql = "CREATE TABLE {$this->table} (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL
        )";

        $pdo->exec($sql);
    }

    /**
     * @inheritDoc
     */
    public function repositoryExists(): bool
    {
        try {
            $pdo = $this->connection->getPdo();
            $pdo->query("SELECT 1 FROM {$this->table} LIMIT 1");
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
