<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Repository;

use Luminor\DDD\Domain\Events\EventSourcedAggregateRoot;
use Luminor\DDD\Domain\Events\EventStoreInterface;
use Luminor\DDD\Domain\Events\SnapshotStoreInterface;

/**
 * Repository for event-sourced aggregates.
 *
 * This repository loads aggregates by replaying their event stream
 * and saves them by persisting new events.
 *
 * @template T of EventSourcedAggregateRoot
 */
abstract class EventSourcedRepository
{
    public function __construct(
        private readonly EventStoreInterface $eventStore,
        private readonly ?SnapshotStoreInterface $snapshotStore = null
    ) {
    }

    /**
     * Find an aggregate by its ID.
     *
     * @return T|null
     */
    public function findById(string $id): ?EventSourcedAggregateRoot
    {
        // Try to load from snapshot first
        $snapshot = $this->snapshotStore?->getSnapshot($id);

        if ($snapshot !== null) {
            $aggregate = $snapshot->getAggregate();
            $version = $snapshot->getVersion();

            // Load events that occurred after the snapshot
            $events = $this->eventStore->getEventsForAggregateFromVersion($id, $version);

            if (!empty($events)) {
                foreach ($events as $event) {
                    $aggregate = $this->applyEventToAggregate($aggregate, $event);
                }
            }

            return $aggregate;
        }

        // No snapshot, load all events
        $events = $this->eventStore->getEventsForAggregate($id);

        if (empty($events)) {
            return null;
        }

        $aggregateClass = $this->getAggregateClass();
        return $aggregateClass::reconstituteFromEvents($events);
    }

    /**
     * Save an aggregate.
     *
     * @param T $aggregate
     */
    public function save(EventSourcedAggregateRoot $aggregate): void
    {
        $events = $aggregate->pullDomainEvents();

        if (empty($events)) {
            return;
        }

        // Store all new events
        $this->eventStore->appendAll($events);

        // Check if we should create a snapshot
        if ($this->snapshotStore !== null && $this->shouldSnapshot($aggregate)) {
            $this->snapshotStore->saveSnapshot(
                $aggregate->getId(),
                $aggregate,
                $aggregate->getVersion()
            );
        }
    }

    /**
     * Get the aggregate class name.
     *
     * @return class-string<T>
     */
    abstract protected function getAggregateClass(): string;

    /**
     * Determine if a snapshot should be created.
     */
    protected function shouldSnapshot(EventSourcedAggregateRoot $aggregate): bool
    {
        // Snapshot every 10 events by default
        return $aggregate->getVersion() % 10 === 0;
    }

    /**
     * Apply an event to an aggregate.
     *
     * @param T $aggregate
     * @return T
     */
    private function applyEventToAggregate(
        EventSourcedAggregateRoot $aggregate,
        mixed $event
    ): EventSourcedAggregateRoot {
        $reflectionClass = new \ReflectionClass($aggregate);
        $method = $reflectionClass->getMethod('applyEvent');
        $method->setAccessible(true);
        $method->invoke($aggregate, $event);

        return $aggregate;
    }
}
