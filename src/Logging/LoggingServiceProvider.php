<?php

declare(strict_types=1);

namespace Luminor\Logging;

use Luminor\Container\AbstractServiceProvider;
use Luminor\Container\ContainerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Service provider for logging services.
 *
 * Registers the log manager and configures logging based on
 * application configuration.
 */
final class LoggingServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(ContainerInterface $container): void
    {
        $container->singleton(LogManager::class, function () use ($container) {
            $config = [];

            // Try to get config from container if available
            if ($container->has('config')) {
                $configRepository = $container->get('config');
                if (method_exists($configRepository, 'get')) {
                    $config = $configRepository->get('logging', []);
                }
            }

            // Provide default configuration if none exists
            if (empty($config)) {
                $config = $this->getDefaultConfig();
            }

            return new LogManager($config);
        });

        // Alias for convenience
        $container->alias(LoggerInterface::class, LogManager::class);
        $container->alias(PsrLoggerInterface::class, LogManager::class);
        $container->alias('log', LogManager::class);
    }

    /**
     * @inheritDoc
     */
    public function provides(): array
    {
        return [
            LogManager::class,
            LoggerInterface::class,
            PsrLoggerInterface::class,
            'log',
        ];
    }

    /**
     * Get default logging configuration.
     *
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        $storagePath = function_exists('storage_path')
            ? storage_path('logs/app.log')
            : getcwd() . '/storage/logs/app.log';

        return [
            'default' => 'file',
            'channels' => [
                'file' => [
                    'driver' => 'file',
                    'path' => $storagePath,
                    'level' => 'debug',
                    'max_files' => 7,
                ],
                'stdout' => [
                    'driver' => 'stdout',
                    'level' => 'debug',
                ],
                'null' => [
                    'driver' => 'null',
                ],
            ],
        ];
    }
}
