<?php

declare(strict_types=1);

namespace Lumina\DDD\Queue;

/**
 * Interface for failed job storage.
 *
 * Defines the contract for storing and managing failed jobs.
 */
interface FailedJobProviderInterface
{
    /**
     * Log a failed job.
     *
     * @param string $connection The connection name
     * @param string $queue The queue name
     * @param string $payload The job payload (JSON)
     * @param \Throwable $exception The exception that caused the failure
     * @return int|null The failed job ID
     */
    public function log(string $connection, string $queue, string $payload, \Throwable $exception): ?int;

    /**
     * Get a list of all failed jobs.
     *
     * @return array<int, array{id: int, connection: string, queue: string, payload: string, exception: string, failed_at: string}>
     */
    public function all(): array;

    /**
     * Get a single failed job.
     *
     * @param int $id The failed job ID
     * @return array{id: int, connection: string, queue: string, payload: string, exception: string, failed_at: string}|null
     */
    public function find(int $id): ?array;

    /**
     * Delete a failed job.
     *
     * @param int $id The failed job ID
     * @return bool
     */
    public function forget(int $id): bool;

    /**
     * Delete all failed jobs.
     */
    public function flush(): void;

    /**
     * Get the count of failed jobs.
     *
     * @return int
     */
    public function count(): int;
}
