<?php

declare(strict_types=1);

namespace Luminor\DDD\Queue;

use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Abstract base class for queueable jobs.
 *
 * Provides default implementations for common job functionality.
 * Extend this class to create your own jobs.
 */
abstract class Job implements JobInterface
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var int|array<int>
     */
    public int|array $backoff = 0;

    /**
     * The queue name this job should be pushed to.
     */
    public ?string $queue = null;

    /**
     * The maximum number of seconds the job can run.
     */
    public ?int $timeout = null;

    /**
     * Whether the job should be unique.
     */
    public bool $unique = false;

    /**
     * @inheritDoc
     */
    abstract public function handle(): void;

    /**
     * @inheritDoc
     */
    public function failed(Throwable $exception): void
    {
        // Override in subclass to handle failure
    }

    /**
     * @inheritDoc
     */
    public function tries(): int
    {
        return $this->tries;
    }

    /**
     * @inheritDoc
     */
    public function backoff(): int|array
    {
        return $this->backoff;
    }

    /**
     * @inheritDoc
     */
    public function queue(): ?string
    {
        return $this->queue;
    }

    /**
     * @inheritDoc
     */
    public function timeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * @inheritDoc
     */
    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * @inheritDoc
     */
    public function uniqueId(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'class' => static::class,
            'data' => $this->serialize(),
            'tries' => $this->tries,
            'backoff' => $this->backoff,
            'queue' => $this->queue,
            'timeout' => $this->timeout,
            'unique' => $this->unique,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $job = static::unserialize($data['data'] ?? []);
        $job->tries = $data['tries'] ?? 3;
        $job->backoff = $data['backoff'] ?? 0;
        $job->queue = $data['queue'] ?? null;
        $job->timeout = $data['timeout'] ?? null;
        $job->unique = $data['unique'] ?? false;

        return $job;
    }

    /**
     * Serialize job-specific data.
     *
     * Override this method to serialize job properties.
     *
     * @return array<string, mixed>
     */
    protected function serialize(): array
    {
        // Get all public properties
        $data = [];
        $reflection = new ReflectionClass($this);
        $excludeProperties = ['tries', 'backoff', 'queue', 'timeout', 'unique'];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if (! in_array($name, $excludeProperties, true)) {
                $data[$name] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * Unserialize job-specific data.
     *
     * Override this method to restore job properties.
     *
     * @param array<string, mixed> $data
     */
    protected static function unserialize(array $data): static
    {
        /** @var static $job */
        $job = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            if (property_exists($job, $key)) {
                $reflection = new ReflectionProperty($job, $key);
                $reflection->setValue($job, $value);
            }
        }

        return $job;
    }

    /**
     * Set the number of times the job may be attempted.
     */
    public function setTries(int $tries): static
    {
        $this->tries = $tries;

        return $this;
    }

    /**
     * Set the backoff time.
     *
     * @param int|array<int> $backoff
     */
    public function setBackoff(int|array $backoff): static
    {
        $this->backoff = $backoff;

        return $this;
    }

    /**
     * Set the queue name.
     */
    public function onQueue(?string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set the timeout.
     */
    public function setTimeout(?int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Mark the job as unique.
     */
    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;

        return $this;
    }
}
