<?php

declare(strict_types=1);

namespace Lumina\DDD\Queue;

/**
 * Marker interface indicating that a job should be queued.
 *
 * Jobs implementing this interface will be dispatched to the queue
 * rather than being executed immediately.
 */
interface ShouldQueue
{
    // Marker interface - no methods required
}
