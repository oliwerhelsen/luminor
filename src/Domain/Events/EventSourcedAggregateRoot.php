<?php

declare(strict_types=1);

namespace Luminor\Domain\Events;

use Luminor\Domain\Abstractions\AggregateRoot;
use Luminor\Domain\Abstractions\DomainEvent;

/**
 * Base class for event-sourced aggregate roots.
 *
 * Event-sourced aggregates derive their state from a stream of events.
 * State is not persisted directly; instead, events are persisted and
 * the aggregate is reconstructed by replaying those events.
 */
abstract class EventSourcedAggregateRoot extends AggregateRoot
{
    private int $version = 0;

    /**
     * Reconstruct the aggregate from its event stream.
     *
     * @param array<int, DomainEvent> $events
     */
    public static function reconstituteFromEvents(array $events): static
    {
        if (empty($events)) {
            throw new \InvalidArgumentException('Cannot reconstitute aggregate from empty event stream');
        }

        $firstEvent = $events[0];
        $aggregateId = $firstEvent->getAggregateId();

        if ($aggregateId === null) {
            throw new \InvalidArgumentException('Cannot reconstitute aggregate: event has no aggregate ID');
        }

        $aggregate = new static($aggregateId);

        foreach ($events as $event) {
            $aggregate->applyEvent($event);
            $aggregate->version++;
        }

        return $aggregate;
    }

    /**
     * Get the current version of the aggregate.
     *
     * The version represents the number of events that have been applied.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Apply an event to the aggregate.
     *
     * This method dispatches to specific apply methods based on the event type.
     * For example, an OrderCreated event would call applyOrderCreated().
     */
    private function applyEvent(DomainEvent $event): void
    {
        $method = $this->getApplyMethod($event);

        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }

    /**
     * Get the apply method name for an event.
     */
    private function getApplyMethod(DomainEvent $event): string
    {
        $eventClass = get_class($event);
        $eventName = substr($eventClass, strrpos($eventClass, '\\') + 1);

        return 'apply' . $eventName;
    }

    /**
     * Record a domain event.
     *
     * Overrides the parent method to also apply the event to the aggregate.
     */
    protected function recordEvent(DomainEvent $event): void
    {
        parent::recordEvent($event);
        $this->applyEvent($event);
        $this->version++;
    }
}
