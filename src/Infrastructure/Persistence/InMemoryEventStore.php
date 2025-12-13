<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Persistence;

use DateTimeImmutable;
use Luminor\DDD\Domain\Abstractions\DomainEvent;
use Luminor\DDD\Domain\Events\EventStoreInterface;

/**
 * In-memory event store implementation.
 *
 * Useful for testing and development. Stores all events in memory
 * and provides the same interface as the database event store.
 */
final class InMemoryEventStore implements EventStoreInterface
{
    /** @var array<int, DomainEvent> */
    private array $events = [];

    /** @var array<string, array<int, DomainEvent>> */
    private array $eventsByAggregate = [];

    /** @var array<string, int> */
    private array $versions = [];

    public function append(DomainEvent $event): void
    {
        $this->events[] = $event;

        $aggregateId = $event->getAggregateId();
        if ($aggregateId !== null) {
            if (!isset($this->eventsByAggregate[$aggregateId])) {
                $this->eventsByAggregate[$aggregateId] = [];
            }
            $this->eventsByAggregate[$aggregateId][] = $event;
            $this->versions[$aggregateId] = ($this->versions[$aggregateId] ?? 0) + 1;
        }
    }

    public function appendAll(array $events): void
    {
        foreach ($events as $event) {
            $this->append($event);
        }
    }

    public function getEventsForAggregate(string $aggregateId): array
    {
        return $this->eventsByAggregate[$aggregateId] ?? [];
    }

    public function getEventsForAggregateFromVersion(string $aggregateId, int $fromVersion): array
    {
        $events = $this->getEventsForAggregate($aggregateId);
        return array_slice($events, $fromVersion);
    }

    public function getEventsByType(string $eventClass): array
    {
        return array_filter(
            $this->events,
            fn(DomainEvent $event) => $event instanceof $eventClass
        );
    }

    public function getEventsAfter(DateTimeImmutable $date): array
    {
        return array_filter(
            $this->events,
            fn(DomainEvent $event) => $event->getOccurredOn() > $date
        );
    }

    public function getEventsBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return array_filter(
            $this->events,
            fn(DomainEvent $event) => $event->getOccurredOn() >= $from && $event->getOccurredOn() <= $to
        );
    }

    public function getAggregateVersion(string $aggregateId): int
    {
        return $this->versions[$aggregateId] ?? 0;
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function countForAggregate(string $aggregateId): int
    {
        return count($this->eventsByAggregate[$aggregateId] ?? []);
    }

    /**
     * Get all events in the store.
     *
     * @return array<int, DomainEvent>
     */
    public function getAllEvents(): array
    {
        return $this->events;
    }

    /**
     * Clear all events from the store.
     *
     * Useful for testing.
     */
    public function clear(): void
    {
        $this->events = [];
        $this->eventsByAggregate = [];
        $this->versions = [];
    }
}
