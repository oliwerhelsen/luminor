<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Events;

use DateTimeImmutable;
use Luminor\DDD\Domain\Events\EventSourcedAggregateRoot;

/**
 * Represents a snapshot of an aggregate at a specific version.
 */
final class Snapshot
{
    public function __construct(
        private readonly string $aggregateId,
        private readonly string $aggregateType,
        private readonly EventSourcedAggregateRoot $aggregate,
        private readonly int $version,
        private readonly DateTimeImmutable $createdAt
    ) {
    }

    /**
     * Create a snapshot from an aggregate.
     */
    public static function take(EventSourcedAggregateRoot $aggregate): self
    {
        return new self(
            aggregateId: $aggregate->getId(),
            aggregateType: get_class($aggregate),
            aggregate: $aggregate,
            version: $aggregate->getVersion(),
            createdAt: new DateTimeImmutable()
        );
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getAggregate(): EventSourcedAggregateRoot
    {
        return $this->aggregate;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
