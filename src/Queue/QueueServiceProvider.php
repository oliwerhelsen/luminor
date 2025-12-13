<?php

declare(strict_types=1);

namespace Luminor\DDD\Queue;

use Luminor\DDD\Container\AbstractServiceProvider;
use Luminor\DDD\Container\ContainerInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Service provider for queue services.
 *
 * Registers the queue manager and worker services.
 */
final class QueueServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(ContainerInterface $container): void
    {
        $container->singleton(QueueManager::class, function (PsrContainerInterface $c) {
            $config = [];

            // Try to get config from container if available
            if ($c->has('config')) {
                $configRepository = $c->get('config');
                if (is_object($configRepository) && method_exists($configRepository, 'get')) {
                    $config = $configRepository->get('queue', []);
                }
            }

            // Provide default configuration if none exists
            if ($config === [] || $config === null) {
                $config = $this->getDefaultConfig();
            }

            return new QueueManager($config);
        });

        // Bind interface to implementation
        $container->bind(QueueInterface::class, function (PsrContainerInterface $c) {
            /** @var QueueManager $manager */
            $manager = $c->get(QueueManager::class);

            return $manager->connection();
        });

        // Register worker
        $container->bind(Worker::class, function (PsrContainerInterface $c) {
            /** @var QueueManager $manager */
            $manager = $c->get(QueueManager::class);

            $failedProvider = $c->has(FailedJobProviderInterface::class)
                ? $c->get(FailedJobProviderInterface::class)
                : null;

            $logger = $c->has('log')
                ? $c->get('log')
                : null;

            return new Worker($manager, $failedProvider, $logger);
        });
    }

    /**
     * @inheritDoc
     */
    public function provides(): array
    {
        return [
            QueueManager::class,
            QueueInterface::class,
            Worker::class,
        ];
    }

    /**
     * Get default queue configuration.
     *
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'default' => 'sync',
            'connections' => [
                'sync' => [
                    'driver' => 'sync',
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => 'jobs',
                    'queue' => 'default',
                    'retry_after' => 90,
                ],
                'redis' => [
                    'driver' => 'redis',
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'database' => 0,
                    'queue' => 'default',
                    'retry_after' => 90,
                ],
            ],
            'failed' => [
                'driver' => 'database',
                'table' => 'failed_jobs',
            ],
        ];
    }
}
