<?php

declare(strict_types=1);

namespace Lumina\DDD\Testing;

use Lumina\DDD\Domain\Abstractions\DomainEvent;
use Lumina\DDD\Domain\Events\EventDispatcherInterface;
use Lumina\DDD\Domain\Events\EventHandlerInterface;

/**
 * In-memory event dispatcher for testing.
 *
 * Records all dispatched events and allows setting up
 * handlers for testing scenarios.
 */
final class InMemoryEventDispatcher implements EventDispatcherInterface
{
    /** @var array<DomainEvent> */
    private array $dispatchedEvents = [];

    /** @var array<class-string, array<callable>> */
    private array $handlers = [];

    /** @var bool */
    private bool $shouldPropagate = true;

    /**
     * @inheritDoc
     */
    public function dispatch(DomainEvent $event): void
    {
        $this->dispatchedEvents[] = $event;

        if (!$this->shouldPropagate) {
            return;
        }

        $eventClass = $event::class;

        // Call registered handlers
        if (isset($this->handlers[$eventClass])) {
            foreach ($this->handlers[$eventClass] as $handler) {
                $handler($event);
            }
        }

        // Also call handlers registered for parent classes/interfaces
        foreach ($this->handlers as $class => $handlers) {
            if ($class !== $eventClass && $event instanceof $class) {
                foreach ($handlers as $handler) {
                    $handler($event);
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function subscribe(string $eventClass, EventHandlerInterface $handler): void
    {
        $this->handlers[$eventClass][] = $handler;
    }

    /**
     * Register a handler callable for an event.
     *
     * @param class-string<DomainEvent> $eventClass
     */
    public function listen(string $eventClass, callable $handler): self
    {
        $this->handlers[$eventClass][] = $handler;
        return $this;
    }

    /**
     * Stop event propagation to handlers.
     */
    public function stopPropagation(): self
    {
        $this->shouldPropagate = false;
        return $this;
    }

    /**
     * Resume event propagation to handlers.
     */
    public function resumePropagation(): self
    {
        $this->shouldPropagate = true;
        return $this;
    }

    /**
     * Check if an event was dispatched.
     *
     * @param class-string<DomainEvent> $eventClass
     */
    public function hasDispatched(string $eventClass): bool
    {
        foreach ($this->dispatchedEvents as $event) {
            if ($event instanceof $eventClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the number of times an event was dispatched.
     *
     * @param class-string<DomainEvent> $eventClass
     */
    public function getDispatchCount(string $eventClass): int
    {
        $count = 0;
        foreach ($this->dispatchedEvents as $event) {
            if ($event instanceof $eventClass) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get all dispatched events.
     *
     * @return array<DomainEvent>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * Get the last dispatched event.
     */
    public function getLastEvent(): ?DomainEvent
    {
        $count = count($this->dispatchedEvents);
        return $count > 0 ? $this->dispatchedEvents[$count - 1] : null;
    }

    /**
     * Get dispatched events of a specific type.
     *
     * @param class-string<DomainEvent> $eventClass
     * @return array<DomainEvent>
     */
    public function getDispatchedOfType(string $eventClass): array
    {
        return array_filter(
            $this->dispatchedEvents,
            fn(DomainEvent $event) => $event instanceof $eventClass
        );
    }

    /**
     * Get the first dispatched event of a specific type.
     *
     * @param class-string<DomainEvent> $eventClass
     */
    public function getFirstOfType(string $eventClass): ?DomainEvent
    {
        foreach ($this->dispatchedEvents as $event) {
            if ($event instanceof $eventClass) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Reset the dispatcher state.
     */
    public function reset(): void
    {
        $this->dispatchedEvents = [];
        $this->handlers = [];
        $this->shouldPropagate = true;
    }

    /**
     * Assert that no events were dispatched.
     *
     * @throws \RuntimeException if events were dispatched
     */
    public function assertNothingDispatched(): void
    {
        if (count($this->dispatchedEvents) > 0) {
            $classes = array_map(
                fn(DomainEvent $e) => $e::class,
                $this->dispatchedEvents
            );
            throw new \RuntimeException(
                sprintf('Expected no events to be dispatched, but [%s] were dispatched.', implode(', ', array_unique($classes)))
            );
        }
    }

    /**
     * Assert that an event was dispatched with specific properties.
     *
     * @param class-string<DomainEvent> $eventClass
     * @param callable(DomainEvent): bool $assertion
     * @throws \RuntimeException if no matching event was found
     */
    public function assertDispatchedWith(string $eventClass, callable $assertion): void
    {
        $events = $this->getDispatchedOfType($eventClass);

        foreach ($events as $event) {
            if ($assertion($event)) {
                return;
            }
        }

        throw new \RuntimeException(
            sprintf('No event of type [%s] matching the assertion was dispatched.', $eventClass)
        );
    }
}
