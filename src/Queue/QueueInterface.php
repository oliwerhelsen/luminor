<?php

declare(strict_types=1);

namespace Luminor\Queue;

/**
 * Interface for queue implementations.
 *
 * Defines the contract for pushing and managing jobs in a queue.
 */
interface QueueInterface
{
    /**
     * Push a job onto the queue.
     *
     * @param JobInterface $job The job to queue
     * @param string|null $queue The queue name (null for default)
     * @return string|int The job ID
     */
    public function push(JobInterface $job, ?string $queue = null): string|int;

    /**
     * Push a job onto the queue with a delay.
     *
     * @param JobInterface $job The job to queue
     * @param int $delay Delay in seconds
     * @param string|null $queue The queue name (null for default)
     * @return string|int The job ID
     */
    public function later(JobInterface $job, int $delay, ?string $queue = null): string|int;

    /**
     * Pop the next job from the queue.
     *
     * @param string|null $queue The queue name (null for default)
     * @return QueuedJob|null The next job or null if empty
     */
    public function pop(?string $queue = null): ?QueuedJob;

    /**
     * Delete a job from the queue.
     *
     * @param string|int $jobId The job ID
     * @param string|null $queue The queue name
     */
    public function delete(string|int $jobId, ?string $queue = null): void;

    /**
     * Release a job back onto the queue.
     *
     * @param QueuedJob $job The job to release
     * @param int $delay Delay in seconds before the job becomes available
     */
    public function release(QueuedJob $job, int $delay = 0): void;

    /**
     * Get the number of jobs in the queue.
     *
     * @param string|null $queue The queue name (null for default)
     * @return int
     */
    public function size(?string $queue = null): int;

    /**
     * Clear all jobs from a queue.
     *
     * @param string|null $queue The queue name (null for default)
     * @return int Number of jobs cleared
     */
    public function clear(?string $queue = null): int;

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function getConnectionName(): string;
}
