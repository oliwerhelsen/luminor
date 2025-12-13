<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Events;

use Luminor\DDD\Domain\Abstractions\AggregateRoot;
use Luminor\DDD\Domain\Abstractions\DomainEvent;

/**
 * Publisher for domain events from aggregate roots.
 *
 * The DomainEventPublisher collects events from aggregates after they
 * have been persisted and dispatches them to the event dispatcher.
 * It can also optionally store events in an event store for event sourcing.
 */
final class DomainEventPublisher
{
    /** @var array<int, DomainEvent> */
    private array $pendingEvents = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?EventStoreInterface $eventStore = null,
    ) {
    }

    /**
     * Collect events from an aggregate root.
     *
     * This should be called after the aggregate has been persisted
     * to collect its domain events for later publishing.
     */
    public function collectEvents(AggregateRoot $aggregate): void
    {
        $events = $aggregate->pullDomainEvents();
        foreach ($events as $event) {
            $this->pendingEvents[] = $event;
        }
    }

    /**
     * Collect events from multiple aggregate roots.
     *
     * @param array<int, AggregateRoot> $aggregates
     */
    public function collectEventsFromAll(array $aggregates): void
    {
        foreach ($aggregates as $aggregate) {
            $this->collectEvents($aggregate);
        }
    }

    /**
     * Publish a single domain event immediately.
     */
    public function publish(DomainEvent $event): void
    {
        $this->storeEvent($event);
        $this->dispatcher->dispatch($event);
    }

    /**
     * Publish multiple domain events immediately.
     *
     * @param array<int, DomainEvent> $events
     */
    public function publishAll(array $events): void
    {
        foreach ($events as $event) {
            $this->storeEvent($event);
        }
        $this->dispatcher->dispatchAll($events);
    }

    /**
     * Publish all pending events that were collected from aggregates.
     */
    public function publishPending(): void
    {
        if (count($this->pendingEvents) === 0) {
            return;
        }

        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        $this->publishAll($events);
    }

    /**
     * Get all pending events without publishing them.
     *
     * @return array<int, DomainEvent>
     */
    public function getPendingEvents(): array
    {
        return $this->pendingEvents;
    }

    /**
     * Check if there are any pending events.
     */
    public function hasPendingEvents(): bool
    {
        return count($this->pendingEvents) > 0;
    }

    /**
     * Clear all pending events without publishing them.
     */
    public function clearPendingEvents(): void
    {
        $this->pendingEvents = [];
    }

    /**
     * Get the count of pending events.
     */
    public function pendingEventCount(): int
    {
        return count($this->pendingEvents);
    }

    /**
     * Store an event in the event store if configured.
     */
    private function storeEvent(DomainEvent $event): void
    {
        $this->eventStore?->append($event);
    }
}
