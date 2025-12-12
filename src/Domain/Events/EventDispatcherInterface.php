<?php

declare(strict_types=1);

namespace Lumina\DDD\Domain\Events;

use Lumina\DDD\Domain\Abstractions\DomainEvent;

/**
 * Interface for dispatching domain events to their handlers.
 *
 * The event dispatcher is responsible for routing domain events to all
 * registered handlers. This enables loose coupling between components
 * that produce events and components that react to them.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch a single domain event to all registered handlers.
     */
    public function dispatch(DomainEvent $event): void;

    /**
     * Dispatch multiple domain events.
     *
     * @param array<int, DomainEvent> $events
     */
    public function dispatchAll(array $events): void;

    /**
     * Register an event handler for a specific event type.
     *
     * @param class-string<DomainEvent> $eventClass The event class to handle
     * @param EventHandlerInterface $handler The handler to register
     */
    public function subscribe(string $eventClass, EventHandlerInterface $handler): void;

    /**
     * Register a handler that receives all events.
     */
    public function subscribeToAll(EventHandlerInterface $handler): void;

    /**
     * Unregister an event handler for a specific event type.
     *
     * @param class-string<DomainEvent> $eventClass The event class
     * @param EventHandlerInterface $handler The handler to unregister
     */
    public function unsubscribe(string $eventClass, EventHandlerInterface $handler): void;

    /**
     * Check if any handlers are registered for an event type.
     *
     * @param class-string<DomainEvent> $eventClass The event class to check
     */
    public function hasHandlers(string $eventClass): bool;
}
