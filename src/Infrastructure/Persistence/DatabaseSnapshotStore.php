<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Persistence;

use DateTimeImmutable;
use Luminor\DDD\Database\ConnectionInterface;
use Luminor\DDD\Domain\Events\EventSourcedAggregateRoot;
use Luminor\DDD\Domain\Events\Snapshot;
use Luminor\DDD\Domain\Events\SnapshotStoreInterface;

/**
 * Database-backed snapshot store implementation.
 */
final class DatabaseSnapshotStore implements SnapshotStoreInterface
{
    private const TABLE_NAME = 'snapshots';

    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    public function saveSnapshot(string $aggregateId, EventSourcedAggregateRoot $aggregate, int $version): void
    {
        $sql = sprintf(
            'INSERT INTO %s (aggregate_id, aggregate_type, version, state, created_at) VALUES (?, ?, ?, ?, ?)',
            self::TABLE_NAME
        );

        $this->connection->execute($sql, [
            $aggregateId,
            get_class($aggregate),
            $version,
            serialize($aggregate),
            (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function getSnapshot(string $aggregateId): ?Snapshot
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE aggregate_id = ? ORDER BY version DESC LIMIT 1',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [$aggregateId]);

        if (empty($rows)) {
            return null;
        }

        return $this->reconstructSnapshot($rows[0]);
    }

    public function getSnapshotAtVersion(string $aggregateId, int $version): ?Snapshot
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE aggregate_id = ? AND version = ? LIMIT 1',
            self::TABLE_NAME
        );

        $rows = $this->connection->query($sql, [$aggregateId, $version]);

        if (empty($rows)) {
            return null;
        }

        return $this->reconstructSnapshot($rows[0]);
    }

    public function deleteSnapshots(string $aggregateId): void
    {
        $sql = sprintf('DELETE FROM %s WHERE aggregate_id = ?', self::TABLE_NAME);
        $this->connection->execute($sql, [$aggregateId]);
    }

    public function deleteSnapshotsOlderThan(string $aggregateId, int $version): void
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE aggregate_id = ? AND version < ?',
            self::TABLE_NAME
        );

        $this->connection->execute($sql, [$aggregateId, $version]);
    }

    /**
     * Reconstruct a snapshot from a database row.
     *
     * @param array<string, mixed> $row
     */
    private function reconstructSnapshot(array $row): Snapshot
    {
        $aggregate = unserialize($row['state']);

        return new Snapshot(
            aggregateId: $row['aggregate_id'],
            aggregateType: $row['aggregate_type'],
            aggregate: $aggregate,
            version: (int) $row['version'],
            createdAt: new DateTimeImmutable($row['created_at'])
        );
    }
}
