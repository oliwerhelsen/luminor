<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Events;

use Luminor\DDD\Domain\Events\EventSourcedAggregateRoot;

/**
 * Interface for persisting aggregate snapshots.
 *
 * Snapshots improve performance by caching the state of an aggregate
 * at a specific version, reducing the need to replay all events.
 */
interface SnapshotStoreInterface
{
    /**
     * Save a snapshot of an aggregate.
     */
    public function saveSnapshot(string $aggregateId, EventSourcedAggregateRoot $aggregate, int $version): void;

    /**
     * Get the latest snapshot for an aggregate.
     */
    public function getSnapshot(string $aggregateId): ?Snapshot;

    /**
     * Get a snapshot at a specific version.
     */
    public function getSnapshotAtVersion(string $aggregateId, int $version): ?Snapshot;

    /**
     * Delete snapshots for an aggregate.
     */
    public function deleteSnapshots(string $aggregateId): void;

    /**
     * Delete snapshots older than a specific version.
     */
    public function deleteSnapshotsOlderThan(string $aggregateId, int $version): void;
}
