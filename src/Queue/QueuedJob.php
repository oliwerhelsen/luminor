<?php

declare(strict_types=1);

namespace Luminor\DDD\Queue;

use DateTimeImmutable;

/**
 * Represents a job that has been pulled from the queue.
 *
 * Contains the job instance along with metadata about
 * the queued job such as attempts and queue name.
 */
final class QueuedJob
{
    /**
     * @param string|int $id The job ID in the queue
     * @param JobInterface $job The actual job instance
     * @param string $queue The queue name
     * @param int $attempts Number of times this job has been attempted
     * @param DateTimeImmutable $reservedAt When the job was reserved
     * @param array<string, mixed> $rawPayload The original payload data
     */
    public function __construct(
        public readonly string|int $id,
        public readonly JobInterface $job,
        public readonly string $queue,
        public readonly int $attempts,
        public readonly DateTimeImmutable $reservedAt,
        public readonly array $rawPayload = [],
    ) {
    }

    /**
     * Get the job instance.
     */
    public function getJob(): JobInterface
    {
        return $this->job;
    }

    /**
     * Get the job ID.
     */
    public function getId(): string|int
    {
        return $this->id;
    }

    /**
     * Get the queue name.
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Get the number of attempts.
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Check if the job has exceeded its maximum attempts.
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->job->tries();
    }

    /**
     * Get the backoff time for the current attempt.
     *
     * @return int Seconds to wait before next attempt
     */
    public function getBackoffTime(): int
    {
        $backoff = $this->job->backoff();

        if (is_int($backoff)) {
            return $backoff;
        }

        // Exponential backoff - get the appropriate delay for this attempt
        $index = min($this->attempts - 1, count($backoff) - 1);

        return $backoff[$index] ?? 0;
    }

    /**
     * Check if the job has timed out.
     */
    public function hasTimedOut(): bool
    {
        $timeout = $this->job->timeout();

        if ($timeout === null) {
            return false;
        }

        $elapsed = time() - $this->reservedAt->getTimestamp();

        return $elapsed >= $timeout;
    }
}
