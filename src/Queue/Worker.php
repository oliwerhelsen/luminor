<?php

declare(strict_types=1);

namespace Luminor\Queue;

use Luminor\Logging\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Queue worker that processes jobs from queues.
 *
 * Handles job execution, retries, failure handling, and graceful shutdown.
 */
final class Worker
{
    private bool $shouldQuit = false;
    private bool $paused = false;

    /** @var array<string, mixed> */
    private array $options = [
        'sleep' => 3,           // Seconds to sleep when no jobs available
        'max_tries' => null,    // Override job tries (null = use job setting)
        'timeout' => 60,        // Default timeout in seconds
        'memory' => 128,        // Memory limit in MB
        'max_jobs' => 0,        // Max jobs before stopping (0 = unlimited)
        'max_time' => 0,        // Max time in seconds (0 = unlimited)
        'stop_on_empty' => false, // Stop when queue is empty
    ];

    private int $jobsProcessed = 0;
    private float $startTime;

    public function __construct(
        private readonly QueueManager $manager,
        private readonly ?FailedJobProviderInterface $failedJobProvider = null,
        private readonly ?PsrLoggerInterface $logger = null,
    ) {
        $this->startTime = microtime(true);
    }

    /**
     * Process jobs from the queue in a loop (daemon mode).
     *
     * @param string $connectionName The connection to use
     * @param string $queue The queue name
     * @param array<string, mixed> $options Worker options
     */
    public function daemon(string $connectionName, string $queue, array $options = []): void
    {
        $this->options = array_merge($this->options, $options);

        $this->registerSignalHandlers();

        while (!$this->shouldQuit) {
            if ($this->paused) {
                $this->sleep($this->options['sleep']);
                continue;
            }

            $job = $this->getNextJob($connectionName, $queue);

            if ($job !== null) {
                $this->processJob($connectionName, $job);
                $this->jobsProcessed++;
            } else {
                $stopOnEmpty = (bool) ($this->options['stop_on_empty'] ?? false);
                if ($stopOnEmpty) {
                    $this->stop();
                    break;
                }
                $this->sleep((int) ($this->options['sleep'] ?? 3));
            }

            if ($this->shouldStop()) {
                $this->stop();
            }
        }
    }

    /**
     * Process the next job from the queue.
     *
     * @param string $connectionName The connection to use
     * @param string $queue The queue name
     * @param array<string, mixed> $options Worker options
     * @return bool Whether a job was processed
     */
    public function runNextJob(string $connectionName, string $queue, array $options = []): bool
    {
        $this->options = array_merge($this->options, $options);

        $job = $this->getNextJob($connectionName, $queue);

        if ($job === null) {
            return false;
        }

        $this->processJob($connectionName, $job);
        return true;
    }

    /**
     * Get the next job from the queue.
     */
    private function getNextJob(string $connectionName, string $queue): ?QueuedJob
    {
        try {
            return $this->manager->connection($connectionName)->pop($queue);
        } catch (\Throwable $e) {
            $this->logger?->error('Error fetching job from queue', [
                'connection' => $connectionName,
                'queue' => $queue,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Process a single job.
     */
    private function processJob(string $connectionName, QueuedJob $queuedJob): void
    {
        $queue = $this->manager->connection($connectionName);
        $job = $queuedJob->getJob();

        $this->logger?->info('Processing job', [
            'job' => get_class($job),
            'id' => $queuedJob->getId(),
            'queue' => $queuedJob->getQueue(),
            'attempt' => $queuedJob->getAttempts(),
        ]);

        try {
            // Execute the job
            $job->handle();

            // Delete the job from the queue
            $queue->delete($queuedJob->getId(), $queuedJob->getQueue());

            $this->logger?->info('Job processed successfully', [
                'job' => get_class($job),
                'id' => $queuedJob->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->handleJobException($connectionName, $queuedJob, $e);
        }
    }

    /**
     * Handle a job that threw an exception.
     */
    private function handleJobException(string $connectionName, QueuedJob $queuedJob, \Throwable $e): void
    {
        $queue = $this->manager->connection($connectionName);
        $job = $queuedJob->getJob();
        $maxTries = $this->options['max_tries'] ?? $job->tries();

        $this->logger?->error('Job failed', [
            'job' => get_class($job),
            'id' => $queuedJob->getId(),
            'attempt' => $queuedJob->getAttempts(),
            'max_tries' => $maxTries,
            'exception' => $e,
        ]);

        // Check if we should retry
        if ($queuedJob->getAttempts() < $maxTries) {
            $backoff = $queuedJob->getBackoffTime();
            $queue->release($queuedJob, $backoff);

            $this->logger?->info('Job released for retry', [
                'job' => get_class($job),
                'id' => $queuedJob->getId(),
                'backoff' => $backoff,
            ]);
        } else {
            // Job has failed all attempts
            $this->failJob($connectionName, $queuedJob, $e);
            $queue->delete($queuedJob->getId(), $queuedJob->getQueue());
        }
    }

    /**
     * Mark a job as failed.
     */
    private function failJob(string $connectionName, QueuedJob $queuedJob, \Throwable $e): void
    {
        $job = $queuedJob->getJob();

        // Call the job's failed method
        try {
            $job->failed($e);
        } catch (\Throwable $failedException) {
            $this->logger?->error('Error in job failed() handler', [
                'job' => get_class($job),
                'exception' => $failedException,
            ]);
        }

        // Log to failed jobs table
        $this->failedJobProvider?->log(
            $connectionName,
            $queuedJob->getQueue(),
            json_encode($queuedJob->rawPayload, JSON_THROW_ON_ERROR),
            $e
        );

        $this->logger?->error('Job failed permanently', [
            'job' => get_class($job),
            'id' => $queuedJob->getId(),
        ]);
    }

    /**
     * Register signal handlers for graceful shutdown.
     */
    private function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, fn() => $this->stop());
        pcntl_signal(SIGINT, fn() => $this->stop());
        pcntl_signal(SIGUSR2, fn() => $this->paused = true);
        pcntl_signal(SIGCONT, fn() => $this->paused = false);
    }

    /**
     * Check if the worker should stop.
     */
    private function shouldStop(): bool
    {
        // Check memory limit
        if ($this->memoryExceeded($this->options['memory'])) {
            return true;
        }

        // Check max jobs
        if ($this->options['max_jobs'] > 0 && $this->jobsProcessed >= $this->options['max_jobs']) {
            return true;
        }

        // Check max time
        if ($this->options['max_time'] > 0) {
            $elapsed = microtime(true) - $this->startTime;
            if ($elapsed >= $this->options['max_time']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if memory has been exceeded.
     */
    private function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Sleep for the specified duration.
     */
    private function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * Stop the worker.
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    /**
     * Pause the worker.
     */
    public function pause(): void
    {
        $this->paused = true;
    }

    /**
     * Resume the worker.
     */
    public function resume(): void
    {
        $this->paused = false;
    }

    /**
     * Get the number of jobs processed.
     */
    public function getJobsProcessed(): int
    {
        return $this->jobsProcessed;
    }
}
