<?php

declare(strict_types=1);

namespace Luminor\Application\Services;

use Luminor\Domain\Events\DomainEventPublisher;
use Luminor\Domain\Events\EventDispatcherInterface;

/**
 * Base class for application services.
 *
 * Application services orchestrate domain operations and coordinate
 * between the domain layer and infrastructure concerns like persistence
 * and event publishing.
 */
abstract class ApplicationService
{
    protected ?DomainEventPublisher $eventPublisher = null;

    /**
     * Set the event publisher for dispatching domain events.
     */
    public function setEventPublisher(DomainEventPublisher $eventPublisher): void
    {
        $this->eventPublisher = $eventPublisher;
    }

    /**
     * Set the event dispatcher (creates a publisher internally).
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->eventPublisher = new DomainEventPublisher($dispatcher);
    }

    /**
     * Get the event publisher.
     */
    protected function getEventPublisher(): ?DomainEventPublisher
    {
        return $this->eventPublisher;
    }

    /**
     * Publish pending events if an event publisher is configured.
     */
    protected function publishEvents(): void
    {
        $this->eventPublisher?->publishPending();
    }

    /**
     * Check if event publishing is enabled.
     */
    protected function hasEventPublisher(): bool
    {
        return $this->eventPublisher !== null;
    }
}
