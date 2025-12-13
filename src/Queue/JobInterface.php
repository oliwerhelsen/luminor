<?php

declare(strict_types=1);

namespace Luminor\DDD\Queue;

use Throwable;

/**
 * Interface for queueable jobs.
 *
 * Defines the contract for jobs that can be pushed onto a queue
 * and processed by a worker.
 */
interface JobInterface
{
    /**
     * Handle the job.
     *
     * This method is called when the job is processed by a worker.
     * Throw an exception to indicate failure.
     */
    public function handle(): void;

    /**
     * Handle a job failure.
     *
     * Called when the job has failed all retry attempts.
     *
     * @param Throwable $exception The exception that caused the failure
     */
    public function failed(Throwable $exception): void;

    /**
     * Get the number of times the job may be attempted.
     */
    public function tries(): int;

    /**
     * Get the number of seconds to wait before retrying.
     *
     * @return int|array<int> Seconds, or array for exponential backoff
     */
    public function backoff(): int|array;

    /**
     * Get the queue name this job should be pushed to.
     *
     * @return string|null Null for default queue
     */
    public function queue(): ?string;

    /**
     * Get the maximum number of seconds the job can run.
     *
     * @return int|null Null for no timeout
     */
    public function timeout(): ?int;

    /**
     * Determine if the job should be unique.
     */
    public function isUnique(): bool;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): ?string;

    /**
     * Serialize the job to an array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Create a job instance from serialized data.
     *
     * @param array<string, mixed> $data The serialized job data
     */
    public static function fromArray(array $data): static;
}
