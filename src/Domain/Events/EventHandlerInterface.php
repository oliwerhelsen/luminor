<?php

declare(strict_types=1);

namespace Lumina\DDD\Domain\Events;

use Lumina\DDD\Domain\Abstractions\DomainEvent;

/**
 * Interface for domain event handlers.
 *
 * Event handlers react to domain events and perform side effects
 * such as sending notifications, updating read models, or triggering
 * other processes.
 */
interface EventHandlerInterface
{
    /**
     * Handle a domain event.
     */
    public function handle(DomainEvent $event): void;

    /**
     * Get the event classes this handler can process.
     *
     * Return an empty array to handle all events.
     *
     * @return array<int, class-string<DomainEvent>>
     */
    public function subscribedTo(): array;
}
