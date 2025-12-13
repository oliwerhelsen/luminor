<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Events;

use DateTimeImmutable;
use Luminor\DDD\Domain\Abstractions\DomainEvent;

/**
 * Wrapper for persisted domain events.
 *
 * StoredEvent adds persistence metadata to domain events such as
 * a sequence number and storage timestamp.
 */
final class StoredEvent
{
    public function __construct(
        private readonly int $sequenceNumber,
        private readonly DomainEvent $event,
        private readonly DateTimeImmutable $storedAt
    ) {
    }

    /**
     * Create a stored event from a domain event.
     */
    public static function fromDomainEvent(int $sequenceNumber, DomainEvent $event): self
    {
        return new self($sequenceNumber, $event, new DateTimeImmutable());
    }

    /**
     * Get the sequence number in the event store.
     */
    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    /**
     * Get the underlying domain event.
     */
    public function getEvent(): DomainEvent
    {
        return $this->event;
    }

    /**
     * Get the event ID.
     */
    public function getEventId(): string
    {
        return $this->event->getEventId();
    }

    /**
     * Get the event type.
     */
    public function getEventType(): string
    {
        return $this->event->getEventType();
    }

    /**
     * Get the aggregate ID.
     */
    public function getAggregateId(): ?string
    {
        return $this->event->getAggregateId();
    }

    /**
     * Get when the event occurred.
     */
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->event->getOccurredOn();
    }

    /**
     * Get when the event was stored.
     */
    public function getStoredAt(): DateTimeImmutable
    {
        return $this->storedAt;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sequenceNumber' => $this->sequenceNumber,
            'storedAt' => $this->storedAt->format(DateTimeImmutable::ATOM),
            'event' => $this->event->toArray(),
        ];
    }
}
