<?php

declare(strict_types=1);

namespace Luminor\Domain\Events;

use Luminor\Domain\Abstractions\DomainEvent;

/**
 * Manages event projections.
 *
 * The projection manager coordinates multiple projectors and ensures
 * events are dispatched to the appropriate projectors.
 */
final class ProjectionManager
{
    /** @var array<string, ProjectorInterface> */
    private array $projectors = [];

    /** @var array<string, array<int, ProjectorInterface>> */
    private array $eventHandlers = [];

    public function __construct(
        private readonly EventStoreInterface $eventStore
    ) {
    }

    /**
     * Register a projector.
     */
    public function registerProjector(ProjectorInterface $projector): void
    {
        $this->projectors[$projector->getName()] = $projector;

        foreach ($projector->getHandledEvents() as $eventClass) {
            if (!isset($this->eventHandlers[$eventClass])) {
                $this->eventHandlers[$eventClass] = [];
            }
            $this->eventHandlers[$eventClass][] = $projector;
        }
    }

    /**
     * Register multiple projectors.
     *
     * @param array<int, ProjectorInterface> $projectors
     */
    public function registerProjectors(array $projectors): void
    {
        foreach ($projectors as $projector) {
            $this->registerProjector($projector);
        }
    }

    /**
     * Project a single event.
     */
    public function projectEvent(DomainEvent $event): void
    {
        $eventClass = get_class($event);

        if (!isset($this->eventHandlers[$eventClass])) {
            return;
        }

        foreach ($this->eventHandlers[$eventClass] as $projector) {
            $projector->project($event);
        }
    }

    /**
     * Project multiple events.
     *
     * @param array<int, DomainEvent> $events
     */
    public function projectEvents(array $events): void
    {
        foreach ($events as $event) {
            $this->projectEvent($event);
        }
    }

    /**
     * Rebuild all projections from the event store.
     */
    public function rebuildAll(): void
    {
        // Reset all projectors
        foreach ($this->projectors as $projector) {
            $projector->reset();
        }

        // Replay all events
        $offset = 0;
        $limit = 100;

        while (true) {
            $events = $this->eventStore->getAllEvents($limit, $offset);

            if (empty($events)) {
                break;
            }

            $this->projectEvents($events);

            $offset += $limit;
        }
    }

    /**
     * Rebuild a specific projection.
     */
    public function rebuild(string $projectorName): void
    {
        if (!isset($this->projectors[$projectorName])) {
            throw new \InvalidArgumentException("Projector not found: {$projectorName}");
        }

        $projector = $this->projectors[$projectorName];
        $projector->reset();

        $offset = 0;
        $limit = 100;

        while (true) {
            $events = $this->eventStore->getAllEvents($limit, $offset);

            if (empty($events)) {
                break;
            }

            foreach ($events as $event) {
                if (in_array(get_class($event), $projector->getHandledEvents(), true)) {
                    $projector->project($event);
                }
            }

            $offset += $limit;
        }
    }

    /**
     * Get all registered projectors.
     *
     * @return array<string, ProjectorInterface>
     */
    public function getProjectors(): array
    {
        return $this->projectors;
    }

    /**
     * Get a specific projector by name.
     */
    public function getProjector(string $name): ?ProjectorInterface
    {
        return $this->projectors[$name] ?? null;
    }
}
