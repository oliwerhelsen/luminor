<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Events;

use Luminor\DDD\Domain\Abstractions\DomainEvent;

/**
 * Interface for event projectors.
 *
 * Projectors build read models from domain events. They listen to
 * specific events and update denormalized views optimized for queries.
 */
interface ProjectorInterface
{
    /**
     * Get the event types this projector handles.
     *
     * @return array<int, class-string<DomainEvent>>
     */
    public function getHandledEvents(): array;

    /**
     * Project an event onto the read model.
     */
    public function project(DomainEvent $event): void;

    /**
     * Reset the projection (clear all data).
     */
    public function reset(): void;

    /**
     * Get the projector name.
     */
    public function getName(): string;
}
