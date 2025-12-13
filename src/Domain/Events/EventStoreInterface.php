<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Events;

use DateTimeImmutable;
use Luminor\DDD\Domain\Abstractions\DomainEvent;

/**
 * Interface for persisting domain events.
 *
 * The event store provides persistence for domain events, enabling
 * event sourcing patterns where the state of an aggregate can be
 * reconstructed by replaying its events.
 */
interface EventStoreInterface
{
    /**
     * Append an event to the store.
     */
    public function append(DomainEvent $event): void;

    /**
     * Append multiple events to the store.
     *
     * @param array<int, DomainEvent> $events
     */
    public function appendAll(array $events): void;

    /**
     * Get all events for a specific aggregate.
     *
     * @param string $aggregateId The aggregate identifier
     *
     * @return array<int, DomainEvent>
     */
    public function getEventsForAggregate(string $aggregateId): array;

    /**
     * Get events for an aggregate starting from a specific version.
     *
     * @param string $aggregateId The aggregate identifier
     * @param int $fromVersion The starting version (exclusive)
     *
     * @return array<int, DomainEvent>
     */
    public function getEventsForAggregateFromVersion(string $aggregateId, int $fromVersion): array;

    /**
     * Get all events of a specific type.
     *
     * @param class-string<DomainEvent> $eventClass The event class
     *
     * @return array<int, DomainEvent>
     */
    public function getEventsByType(string $eventClass): array;

    /**
     * Get events that occurred after a specific date.
     *
     * @return array<int, DomainEvent>
     */
    public function getEventsAfter(DateTimeImmutable $date): array;

    /**
     * Get events that occurred between two dates.
     *
     * @return array<int, DomainEvent>
     */
    public function getEventsBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Get the current version (event count) for an aggregate.
     *
     * @param string $aggregateId The aggregate identifier
     */
    public function getAggregateVersion(string $aggregateId): int;

    /**
     * Count total events in the store.
     */
    public function count(): int;

    /**
     * Count events for a specific aggregate.
     *
     * @param string $aggregateId The aggregate identifier
     */
    public function countForAggregate(string $aggregateId): int;
}
