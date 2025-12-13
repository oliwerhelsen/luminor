<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\EventBus;

use Luminor\DDD\Domain\Abstractions\DomainEvent;
use Luminor\DDD\Domain\Events\EventDispatcherInterface;
use Luminor\DDD\Domain\Events\EventHandlerInterface;
use Luminor\DDD\Domain\Events\EventSubscriberInterface;

/**
 * Simple in-memory event dispatcher implementation.
 *
 * Dispatches domain events to all registered handlers.
 */
final class SimpleEventDispatcher implements EventDispatcherInterface
{
    /** @var array<class-string<DomainEvent>, array<int, EventHandlerInterface>> */
    private array $handlers = [];

    /** @var array<int, EventHandlerInterface> */
    private array $globalHandlers = [];

    /** @var array<class-string<DomainEvent>, array<int, callable(DomainEvent): void>> */
    private array $callableHandlers = [];

    /**
     * @inheritDoc
     */
    public function dispatch(DomainEvent $event): void
    {
        $eventClass = $event::class;

        // Dispatch to specific handlers
        if (isset($this->handlers[$eventClass])) {
            foreach ($this->handlers[$eventClass] as $handler) {
                $handler->handle($event);
            }
        }

        // Dispatch to callable handlers
        if (isset($this->callableHandlers[$eventClass])) {
            foreach ($this->callableHandlers[$eventClass] as $callable) {
                $callable($event);
            }
        }

        // Dispatch to global handlers
        foreach ($this->globalHandlers as $handler) {
            $handler->handle($event);
        }
    }

    /**
     * @inheritDoc
     */
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    /**
     * @inheritDoc
     */
    public function subscribe(string $eventClass, EventHandlerInterface $handler): void
    {
        if (!isset($this->handlers[$eventClass])) {
            $this->handlers[$eventClass] = [];
        }

        $this->handlers[$eventClass][] = $handler;
    }

    /**
     * Subscribe a callable handler to an event type.
     *
     * @param class-string<DomainEvent> $eventClass
     * @param callable(DomainEvent): void $callable
     */
    public function subscribeCallable(string $eventClass, callable $callable): void
    {
        if (!isset($this->callableHandlers[$eventClass])) {
            $this->callableHandlers[$eventClass] = [];
        }

        $this->callableHandlers[$eventClass][] = $callable;
    }

    /**
     * @inheritDoc
     */
    public function subscribeToAll(EventHandlerInterface $handler): void
    {
        $this->globalHandlers[] = $handler;
    }

    /**
     * Register an event subscriber.
     *
     * The subscriber's getSubscribedEvents() method defines which events
     * it handles and which methods to call.
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventClass => $methods) {
            if (is_string($methods)) {
                $methods = [$methods];
            }

            foreach ($methods as $method) {
                $this->subscribeCallable(
                    $eventClass,
                    fn(DomainEvent $event) => $subscriber->$method($event)
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function unsubscribe(string $eventClass, EventHandlerInterface $handler): void
    {
        if (!isset($this->handlers[$eventClass])) {
            return;
        }

        $this->handlers[$eventClass] = array_filter(
            $this->handlers[$eventClass],
            fn(EventHandlerInterface $h) => $h !== $handler
        );
    }

    /**
     * Remove a handler from all events.
     */
    public function unsubscribeFromAll(EventHandlerInterface $handler): void
    {
        // Remove from specific handlers
        foreach ($this->handlers as $eventClass => $handlers) {
            $this->handlers[$eventClass] = array_filter(
                $handlers,
                fn(EventHandlerInterface $h) => $h !== $handler
            );
        }

        // Remove from global handlers
        $this->globalHandlers = array_filter(
            $this->globalHandlers,
            fn(EventHandlerInterface $h) => $h !== $handler
        );
    }

    /**
     * @inheritDoc
     */
    public function hasHandlers(string $eventClass): bool
    {
        return (isset($this->handlers[$eventClass]) && count($this->handlers[$eventClass]) > 0)
            || (isset($this->callableHandlers[$eventClass]) && count($this->callableHandlers[$eventClass]) > 0)
            || count($this->globalHandlers) > 0;
    }

    /**
     * Get the number of handlers for an event type.
     *
     * @param class-string<DomainEvent> $eventClass
     */
    public function getHandlerCount(string $eventClass): int
    {
        $count = 0;

        if (isset($this->handlers[$eventClass])) {
            $count += count($this->handlers[$eventClass]);
        }

        if (isset($this->callableHandlers[$eventClass])) {
            $count += count($this->callableHandlers[$eventClass]);
        }

        return $count;
    }

    /**
     * Remove all handlers.
     */
    public function clear(): void
    {
        $this->handlers = [];
        $this->callableHandlers = [];
        $this->globalHandlers = [];
    }
}
