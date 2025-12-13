<?php

declare(strict_types=1);

namespace Luminor\DDD\Queue\Drivers;

use DateTimeImmutable;
use Luminor\DDD\Queue\JobInterface;
use Luminor\DDD\Queue\QueuedJob;
use Luminor\DDD\Queue\QueueInterface;
use Redis;
use RuntimeException;

/**
 * Redis-backed queue driver.
 *
 * Uses Redis lists for efficient queue operations.
 * Supports both Predis and the phpredis extension.
 */
class RedisQueue implements QueueInterface
{
    protected mixed $redis = null;

    protected string $prefix;

    protected string $defaultQueue;

    protected int $retryAfter;

    protected string $connectionName;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param array<string, mixed> $config Configuration options:
     *                                     - host: Redis host (default: 127.0.0.1)
     *                                     - port: Redis port (default: 6379)
     *                                     - password: Redis password (optional)
     *                                     - database: Redis database number (default: 0)
     *                                     - prefix: Key prefix (default: luminor_queue:)
     *                                     - queue: Default queue name (default: default)
     *                                     - retry_after: Seconds before a reserved job is released (default: 90)
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'luminor_queue:';
        $this->defaultQueue = $config['queue'] ?? 'default';
        $this->retryAfter = $config['retry_after'] ?? 90;
        $this->connectionName = $config['connection_name'] ?? 'redis';

        if (isset($config['client'])) {
            $this->redis = $config['client'];
        }
    }

    /**
     * Get or create the Redis connection.
     */
    protected function getRedis(): mixed
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        // Try Predis first
        if (class_exists(\Predis\Client::class)) {
            $this->redis = $this->createPredisClient();

            return $this->redis;
        }

        // Try phpredis extension
        if (extension_loaded('redis')) {
            $this->redis = $this->createPhpRedisClient();

            return $this->redis;
        }

        throw new RuntimeException(
            'Redis queue requires either predis/predis package or the phpredis extension.',
        );
    }

    /**
     * Create a Predis client.
     *
     * @return object The Predis client
     */
    protected function createPredisClient(): object
    {
        $parameters = [
            'host' => $this->config['host'] ?? '127.0.0.1',
            'port' => $this->config['port'] ?? 6379,
            'database' => $this->config['database'] ?? 0,
        ];

        if (isset($this->config['password'])) {
            $parameters['password'] = $this->config['password'];
        }

        /** @phpstan-ignore-next-line Class may not exist when predis is not installed */
        return new \Predis\Client($parameters);
    }

    /**
     * Create a phpredis client.
     */
    protected function createPhpRedisClient(): Redis
    {
        $redis = new Redis();

        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 6379;

        $redis->connect($host, $port);

        if (isset($this->config['password'])) {
            $redis->auth($this->config['password']);
        }

        if (isset($this->config['database'])) {
            $redis->select($this->config['database']);
        }

        return $redis;
    }

    /**
     * @inheritDoc
     */
    public function push(JobInterface $job, ?string $queue = null): string|int
    {
        return $this->pushRaw($this->createPayload($job), $queue);
    }

    /**
     * @inheritDoc
     */
    public function later(JobInterface $job, int $delay, ?string $queue = null): string|int
    {
        $queue ??= $this->defaultQueue;
        $payload = $this->createPayload($job);
        $id = $this->generateJobId();

        // Store in delayed sorted set with score as availability time
        $this->getRedis()->zadd(
            $this->prefix . $queue . ':delayed',
            [json_encode(['id' => $id, 'payload' => $payload]) => time() + $delay],
        );

        return $id;
    }

    /**
     * Push a raw payload onto the queue.
     */
    protected function pushRaw(string $payload, ?string $queue = null): string|int
    {
        $queue ??= $this->defaultQueue;
        $id = $this->generateJobId();

        $this->getRedis()->rpush(
            $this->prefix . $queue,
            json_encode(['id' => $id, 'payload' => $payload, 'attempts' => 0]),
        );

        return $id;
    }

    /**
     * @inheritDoc
     */
    public function pop(?string $queue = null): ?QueuedJob
    {
        $queue ??= $this->defaultQueue;

        // First, migrate delayed jobs that are now ready
        $this->migrateDelayedJobs($queue);

        // Pop from the main queue
        $job = $this->getRedis()->lpop($this->prefix . $queue);

        if ($job === null || $job === false) {
            return null;
        }

        $data = json_decode($job, true, 512, JSON_THROW_ON_ERROR);
        $data['attempts'] = ($data['attempts'] ?? 0) + 1;

        // Store in reserved set
        $this->getRedis()->zadd(
            $this->prefix . $queue . ':reserved',
            [json_encode($data) => time() + $this->retryAfter],
        );

        return $this->createQueuedJob($data, $queue);
    }

    /**
     * @inheritDoc
     */
    public function delete(string|int $jobId, ?string $queue = null): void
    {
        $queue ??= $this->defaultQueue;

        // Remove from reserved set by scanning for matching job ID
        $reserved = $this->getRedis()->zrange($this->prefix . $queue . ':reserved', 0, -1);

        foreach ($reserved as $item) {
            $data = json_decode($item, true);
            if (isset($data['id']) && $data['id'] === $jobId) {
                $this->getRedis()->zrem($this->prefix . $queue . ':reserved', $item);
                break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function release(QueuedJob $job, int $delay = 0): void
    {
        $queue = $job->getQueue();

        // Remove from reserved
        $this->delete($job->getId(), $queue);

        // Re-add to queue
        $payload = json_encode($job->rawPayload);

        if ($delay > 0) {
            $this->getRedis()->zadd(
                $this->prefix . $queue . ':delayed',
                [json_encode(['id' => $job->getId(), 'payload' => $payload, 'attempts' => $job->getAttempts()]) => time() + $delay],
            );
        } else {
            $this->getRedis()->rpush(
                $this->prefix . $queue,
                json_encode(['id' => $job->getId(), 'payload' => $payload, 'attempts' => $job->getAttempts()]),
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function size(?string $queue = null): int
    {
        $queue ??= $this->defaultQueue;

        return (int) $this->getRedis()->llen($this->prefix . $queue);
    }

    /**
     * @inheritDoc
     */
    public function clear(?string $queue = null): int
    {
        $queue ??= $this->defaultQueue;
        $size = $this->size($queue);

        $this->getRedis()->del([
            $this->prefix . $queue,
            $this->prefix . $queue . ':reserved',
            $this->prefix . $queue . ':delayed',
        ]);

        return $size;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Migrate delayed jobs that are now ready.
     */
    protected function migrateDelayedJobs(string $queue): void
    {
        $now = time();
        $delayedKey = $this->prefix . $queue . ':delayed';
        $queueKey = $this->prefix . $queue;

        // Get jobs that are ready
        $ready = $this->getRedis()->zrangebyscore($delayedKey, '-inf', (string) $now);

        foreach ($ready as $item) {
            // Remove from delayed and add to main queue
            $this->getRedis()->zrem($delayedKey, $item);
            $this->getRedis()->rpush($queueKey, $item);
        }

        // Also check for expired reserved jobs
        $this->migrateExpiredReserved($queue);
    }

    /**
     * Migrate expired reserved jobs back to the main queue.
     */
    protected function migrateExpiredReserved(string $queue): void
    {
        $now = time();
        $reservedKey = $this->prefix . $queue . ':reserved';
        $queueKey = $this->prefix . $queue;

        // Get expired reservations
        $expired = $this->getRedis()->zrangebyscore($reservedKey, '-inf', (string) $now);

        foreach ($expired as $item) {
            $this->getRedis()->zrem($reservedKey, $item);
            $this->getRedis()->rpush($queueKey, $item);
        }
    }

    /**
     * Create the JSON payload for a job.
     */
    protected function createPayload(JobInterface $job): string
    {
        return json_encode($job->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate a unique job ID.
     */
    protected function generateJobId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Create a QueuedJob from raw data.
     *
     * @param array<string, mixed> $data
     */
    protected function createQueuedJob(array $data, string $queue): QueuedJob
    {
        $payload = is_string($data['payload'])
            ? json_decode($data['payload'], true, 512, JSON_THROW_ON_ERROR)
            : $data['payload'];

        $jobClass = $payload['class'];

        if (! class_exists($jobClass)) {
            throw new RuntimeException(sprintf('Job class [%s] not found.', $jobClass));
        }

        if (! is_subclass_of($jobClass, JobInterface::class)) {
            throw new RuntimeException(sprintf('Job class [%s] must implement JobInterface.', $jobClass));
        }

        /** @var JobInterface $job */
        $job = $jobClass::fromArray($payload);

        return new QueuedJob(
            id: $data['id'],
            job: $job,
            queue: $queue,
            attempts: (int) ($data['attempts'] ?? 1),
            reservedAt: new DateTimeImmutable(),
            rawPayload: $payload,
        );
    }
}
