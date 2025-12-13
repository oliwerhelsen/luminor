<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Abstractions;

/**
 * Base class for aggregate roots.
 *
 * An Aggregate Root is the entry point to an aggregate - a cluster of domain objects
 * that can be treated as a single unit. The aggregate root guarantees the consistency
 * of changes being made within the aggregate by forbidding external objects from holding
 * references to its members.
 *
 * Aggregate roots can record domain events that represent changes within the aggregate.
 *
 * @template TId of mixed
 *
 * @extends Entity<TId>
 */
abstract class AggregateRoot extends Entity
{
    /** @var array<int, DomainEvent> */
    private array $domainEvents = [];

    /**
     * Record a domain event.
     *
     * The event will be stored and can be retrieved and cleared after the aggregate
     * has been persisted.
     */
    protected function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Get all recorded domain events.
     *
     * @return array<int, DomainEvent>
     */
    public function getDomainEvents(): array
    {
        return $this->domainEvents;
    }

    /**
     * Clear all recorded domain events.
     *
     * This should be called after the events have been dispatched.
     */
    public function clearDomainEvents(): void
    {
        $this->domainEvents = [];
    }

    /**
     * Pull all recorded domain events and clear them.
     *
     * @return array<int, DomainEvent>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Check if the aggregate has any recorded domain events.
     */
    public function hasDomainEvents(): bool
    {
        return count($this->domainEvents) > 0;
    }
}
