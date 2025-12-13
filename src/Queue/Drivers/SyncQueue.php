<?php

declare(strict_types=1);

namespace Luminor\DDD\Queue\Drivers;

use Luminor\DDD\Queue\JobInterface;
use Luminor\DDD\Queue\QueuedJob;
use Luminor\DDD\Queue\QueueInterface;

/**
 * Synchronous queue driver.
 *
 * Executes jobs immediately in the same request.
 * Useful for local development and testing.
 */
final class SyncQueue implements QueueInterface
{
    private string $connectionName;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->connectionName = $config['connection_name'] ?? 'sync';
    }

    /**
     * @inheritDoc
     */
    public function push(JobInterface $job, ?string $queue = null): string|int
    {
        // Execute immediately
        $job->handle();
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function later(JobInterface $job, int $delay, ?string $queue = null): string|int
    {
        // For sync, we just execute immediately (ignore delay)
        return $this->push($job, $queue);
    }

    /**
     * @inheritDoc
     */
    public function pop(?string $queue = null): ?QueuedJob
    {
        // Sync queue doesn't store jobs
        return null;
    }

    /**
     * @inheritDoc
     */
    public function delete(string|int $jobId, ?string $queue = null): void
    {
        // Nothing to delete
    }

    /**
     * @inheritDoc
     */
    public function release(QueuedJob $job, int $delay = 0): void
    {
        // Re-execute immediately
        $job->getJob()->handle();
    }

    /**
     * @inheritDoc
     */
    public function size(?string $queue = null): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function clear(?string $queue = null): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }
}
