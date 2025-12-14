<?php

declare(strict_types=1);

namespace Luminor\Domain\Events;

use Luminor\Domain\Abstractions\DomainEvent;

/**
 * Base class for event projectors.
 *
 * Provides a convenient way to build projectors with method-based
 * event handling (similar to aggregate apply methods).
 */
abstract class AbstractProjector implements ProjectorInterface
{
    public function project(DomainEvent $event): void
    {
        $method = $this->getProjectionMethod($event);

        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }

    public function getName(): string
    {
        $className = static::class;
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Get the projection method name for an event.
     */
    private function getProjectionMethod(DomainEvent $event): string
    {
        $eventClass = get_class($event);
        $eventName = substr($eventClass, strrpos($eventClass, '\\') + 1);

        return 'when' . $eventName;
    }
}
