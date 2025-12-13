<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Persistence;

use Luminor\DDD\Domain\Events\EventSourcedAggregateRoot;
use Luminor\DDD\Domain\Events\Snapshot;
use Luminor\DDD\Domain\Events\SnapshotStoreInterface;

/**
 * In-memory snapshot store implementation.
 *
 * Useful for testing and development.
 */
final class InMemorySnapshotStore implements SnapshotStoreInterface
{
    /** @var array<string, array<int, Snapshot>> */
    private array $snapshots = [];

    public function saveSnapshot(string $aggregateId, EventSourcedAggregateRoot $aggregate, int $version): void
    {
        if (!isset($this->snapshots[$aggregateId])) {
            $this->snapshots[$aggregateId] = [];
        }

        $this->snapshots[$aggregateId][$version] = Snapshot::take($aggregate);
    }

    public function getSnapshot(string $aggregateId): ?Snapshot
    {
        if (!isset($this->snapshots[$aggregateId]) || empty($this->snapshots[$aggregateId])) {
            return null;
        }

        // Get the latest snapshot
        $versions = array_keys($this->snapshots[$aggregateId]);
        rsort($versions);

        return $this->snapshots[$aggregateId][$versions[0]];
    }

    public function getSnapshotAtVersion(string $aggregateId, int $version): ?Snapshot
    {
        return $this->snapshots[$aggregateId][$version] ?? null;
    }

    public function deleteSnapshots(string $aggregateId): void
    {
        unset($this->snapshots[$aggregateId]);
    }

    public function deleteSnapshotsOlderThan(string $aggregateId, int $version): void
    {
        if (!isset($this->snapshots[$aggregateId])) {
            return;
        }

        foreach ($this->snapshots[$aggregateId] as $snapshotVersion => $snapshot) {
            if ($snapshotVersion < $version) {
                unset($this->snapshots[$aggregateId][$snapshotVersion]);
            }
        }
    }

    /**
     * Clear all snapshots.
     *
     * Useful for testing.
     */
    public function clear(): void
    {
        $this->snapshots = [];
    }
}
