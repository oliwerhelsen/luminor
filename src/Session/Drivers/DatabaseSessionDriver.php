<?php

declare(strict_types=1);

namespace Luminor\DDD\Session\Drivers;

use Luminor\DDD\Session\SessionDriver;
use Luminor\DDD\Database\ConnectionInterface;
use PDO;

/**
 * Database Session Driver
 *
 * Stores session data in a database table.
 */
final class DatabaseSessionDriver implements SessionDriver
{
    private ConnectionInterface $connection;
    private string $table;

    public function __construct(
        ConnectionInterface $connection,
        string $table = 'sessions'
    ) {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * @inheritDoc
     */
    public function read(string $sessionId): array
    {
        $stmt = $this->connection->query(
            "SELECT data FROM {$this->table} WHERE id = ? AND expires_at > ?",
            [$sessionId, time()]
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return [];
        }

        $data = unserialize($row['data']);

        return is_array($data) ? $data : [];
    }

    /**
     * @inheritDoc
     */
    public function write(string $sessionId, array $data): bool
    {
        $expiresAt = time() + 7200; // 2 hours
        $serialized = serialize($data);

        // Try to update first
        $updated = $this->connection->statement(
            "UPDATE {$this->table} SET data = ?, expires_at = ? WHERE id = ?",
            [$serialized, $expiresAt, $sessionId]
        );

        if ($updated > 0) {
            return true;
        }

        // Insert if not exists
        try {
            $this->connection->statement(
                "INSERT INTO {$this->table} (id, data, expires_at) VALUES (?, ?, ?)",
                [$sessionId, $serialized, $expiresAt]
            );
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function destroy(string $sessionId): bool
    {
        $this->connection->statement(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$sessionId]
        );

        return true;
    }

    /**
     * @inheritDoc
     */
    public function gc(int $maxLifetime): int
    {
        return $this->connection->statement(
            "DELETE FROM {$this->table} WHERE expires_at < ?",
            [time()]
        );
    }
}
