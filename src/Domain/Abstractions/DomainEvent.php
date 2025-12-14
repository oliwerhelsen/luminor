<?php

declare(strict_types=1);

namespace Luminor\Domain\Abstractions;

use DateTimeImmutable;

/**
 * Base class for domain events.
 *
 * Domain events represent something that happened in the domain that domain experts care about.
 * They are immutable and named in the past tense.
 */
abstract class DomainEvent
{
    private readonly string $eventId;
    private readonly DateTimeImmutable $occurredOn;

    /** @var array<string, mixed> */
    private array $metadata = [];

    /**
     * @param string|null $aggregateId The ID of the aggregate that raised this event
     * @param array<string, mixed> $metadata Additional metadata for the event
     */
    public function __construct(
        private readonly ?string $aggregateId = null,
        array $metadata = []
    ) {
        $this->eventId = $this->generateEventId();
        $this->occurredOn = new DateTimeImmutable();
        $this->metadata = $metadata;
    }

    /**
     * Get the unique event identifier.
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * Get the timestamp when the event occurred.
     */
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    /**
     * Get the ID of the aggregate that raised this event.
     */
    public function getAggregateId(): ?string
    {
        return $this->aggregateId;
    }

    /**
     * Get the event name (typically the class name without namespace).
     */
    public function getEventName(): string
    {
        $parts = explode('\\', static::class);
        return end($parts);
    }

    /**
     * Get the fully qualified event type.
     */
    public function getEventType(): string
    {
        return static::class;
    }

    /**
     * Convert the event to an array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'eventType' => $this->getEventType(),
            'eventName' => $this->getEventName(),
            'aggregateId' => $this->aggregateId,
            'occurredOn' => $this->occurredOn->format(DateTimeImmutable::ATOM),
            'payload' => $this->getPayload(),
        ];
    }

    /**
     * Get the event payload.
     *
     * Override this method to include event-specific data.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return [];
    }

    /**
     * Get event metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Add metadata to the event.
     *
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $clone = clone $this;
        $clone->metadata = array_merge($this->metadata, $metadata);
        return $clone;
    }

    /**
     * Generate a unique event identifier.
     */
    private function generateEventId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
