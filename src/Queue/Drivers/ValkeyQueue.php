<?php

declare(strict_types=1);

namespace Luminor\Queue\Drivers;

/**
 * Valkey-backed queue driver.
 *
 * Valkey is a Redis-compatible in-memory data store.
 * This driver extends RedisQueue with Valkey-specific defaults.
 *
 * @see https://valkey.io/
 */
final class ValkeyQueue extends RedisQueue
{
    /**
     * @param array<string, mixed> $config Configuration options:
     *                                      - host: Valkey host (default: 127.0.0.1)
     *                                      - port: Valkey port (default: 6379)
     *                                      - password: Valkey password (optional)
     *                                      - database: Valkey database number (default: 0)
     *                                      - prefix: Key prefix (default: luminor_queue:)
     *                                      - queue: Default queue name (default: default)
     *                                      - retry_after: Seconds before a reserved job is released (default: 90)
     *                                      - tls: TLS configuration (optional)
     */
    public function __construct(array $config = [])
    {
        // Valkey uses the same protocol as Redis
        // Just update the connection name to identify it as Valkey
        $config['connection_name'] = $config['connection_name'] ?? 'valkey';

        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Create a Predis client with Valkey-specific options.
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

        // Support TLS connections for Valkey
        if (isset($this->config['tls'])) {
            $parameters['scheme'] = 'tls';

            $tlsConfig = $this->config['tls'];
            if (is_array($tlsConfig)) {
                $parameters['ssl'] = $tlsConfig;
            }
        }

        $options = [];

        // Valkey cluster support
        $clusterEnabled = $this->config['cluster'] ?? false;
        if ($clusterEnabled === true) {
            $options['cluster'] = 'redis';
        }

        /** @phpstan-ignore-next-line Class may not exist when predis is not installed */
        return new \Predis\Client($parameters, $options);
    }

    /**
     * Create a phpredis client with Valkey-specific options.
     */
    protected function createPhpRedisClient(): \Redis
    {
        $redis = new \Redis();

        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 6379;
        $timeout = $this->config['timeout'] ?? 0.0;
        $persistent = $this->config['persistent'] ?? false;

        // Support TLS connections
        if (isset($this->config['tls'])) {
            $host = 'tls://' . $host;
        }

        if ($persistent) {
            $redis->pconnect($host, $port, $timeout);
        } else {
            $redis->connect($host, $port, $timeout);
        }

        if (isset($this->config['password'])) {
            $redis->auth($this->config['password']);
        }

        if (isset($this->config['database'])) {
            $redis->select($this->config['database']);
        }

        // Set Valkey-specific client name
        $redis->client('SETNAME', 'luminor-queue');

        return $redis;
    }
}
