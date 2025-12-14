<?php

declare(strict_types=1);

namespace Luminor\Queue;

use Luminor\Queue\Drivers\DatabaseQueue;
use Luminor\Queue\Drivers\RedisQueue;
use Luminor\Queue\Drivers\SyncQueue;
use Luminor\Queue\Drivers\ValkeyQueue;
use RuntimeException;

/**
 * Queue manager that handles multiple queue connections.
 *
 * Provides a central point for managing queue connections and
 * resolving the appropriate driver based on configuration.
 */
final class QueueManager
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, QueueInterface> */
    private array $connections = [];

    private string $defaultConnection;

    /** @var array<string, callable> */
    private array $customDrivers = [];

    /**
     * @param array<string, mixed> $config Queue configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultConnection = $config['default'] ?? 'database';
    }

    /**
     * Get a queue connection instance.
     *
     * @param string|null $name The connection name (null for default)
     * @return QueueInterface
     */
    public function connection(?string $name = null): QueueInterface
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    /**
     * Push a job onto the default queue.
     *
     * @param JobInterface $job The job to queue
     * @param string|null $queue The queue name
     * @return string|int The job ID
     */
    public function push(JobInterface $job, ?string $queue = null): string|int
    {
        return $this->connection()->push($job, $queue ?? $job->queue());
    }

    /**
     * Push a job onto the queue with a delay.
     *
     * @param JobInterface $job The job to queue
     * @param int $delay Delay in seconds
     * @param string|null $queue The queue name
     * @return string|int The job ID
     */
    public function later(JobInterface $job, int $delay, ?string $queue = null): string|int
    {
        return $this->connection()->later($job, $delay, $queue ?? $job->queue());
    }

    /**
     * Register a custom queue driver.
     *
     * @param string $name The driver name
     * @param callable $callback Factory callback: fn(array $config) => QueueInterface
     */
    public function extend(string $name, callable $callback): self
    {
        $this->customDrivers[$name] = $callback;
        return $this;
    }

    /**
     * Resolve a queue connection by name.
     *
     * @param string $name The connection name
     * @return QueueInterface
     * @throws RuntimeException If the connection is not configured
     */
    private function resolve(string $name): QueueInterface
    {
        $connectionConfig = $this->config['connections'][$name] ?? null;

        if ($connectionConfig === null) {
            throw new RuntimeException(sprintf('Queue connection [%s] is not configured.', $name));
        }

        $driver = $connectionConfig['driver'] ?? $name;
        $connectionConfig['connection_name'] = $name;

        return $this->createDriver($driver, $connectionConfig);
    }

    /**
     * Create a driver instance.
     *
     * @param string $driver The driver name
     * @param array<string, mixed> $config The driver configuration
     * @return QueueInterface
     * @throws RuntimeException If the driver is not supported
     */
    private function createDriver(string $driver, array $config): QueueInterface
    {
        // Check custom drivers first
        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($config);
        }

        return match ($driver) {
            'sync' => new SyncQueue($config),
            'database' => new DatabaseQueue($config),
            'redis' => new RedisQueue($config),
            'valkey' => new ValkeyQueue($config),
            default => throw new RuntimeException(sprintf('Queue driver [%s] is not supported.', $driver)),
        };
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * Set the default connection name.
     *
     * @param string $name The connection name
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Get the configuration for a connection.
     *
     * @param string $name The connection name
     * @return array<string, mixed>|null
     */
    public function getConnectionConfig(string $name): ?array
    {
        return $this->config['connections'][$name] ?? null;
    }
}
