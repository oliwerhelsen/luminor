<?php

declare(strict_types=1);

namespace Luminor\Database;

use PDO;
use PDOException;

/**
 * Database Connection
 *
 * Wraps a PDO connection with additional functionality.
 */
final class Connection implements ConnectionInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Create a connection from DSN.
     *
     * @param string $dsn The DSN string
     * @param string|null $username The username
     * @param string|null $password The password
     * @param array<int, mixed> $options PDO options
     * @return self
     */
    public static function create(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = []
    ): self {
        $pdo = new PDO($dsn, $username, $password, $options);
        return new self($pdo);
    }

    /**
     * @inheritDoc
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @inheritDoc
     */
    public function query(string $query, array $bindings = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);
        return $stmt;
    }

    /**
     * @inheritDoc
     */
    public function statement(string $query, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * @inheritDoc
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * @inheritDoc
     */
    public function getDriverName(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
