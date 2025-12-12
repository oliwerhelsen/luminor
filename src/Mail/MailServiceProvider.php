<?php

declare(strict_types=1);

namespace Lumina\DDD\Mail;

use Lumina\DDD\Container\AbstractServiceProvider;
use Lumina\DDD\Container\ContainerInterface;
use Lumina\DDD\Queue\QueueManager;

/**
 * Service provider for mail services.
 *
 * Registers the mailer and configures transports.
 */
final class MailServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(ContainerInterface $container): void
    {
        $container->singleton(Mailer::class, function () use ($container) {
            $config = [];

            // Try to get config from container if available
            if ($container->has('config')) {
                $configRepository = $container->get('config');
                if (method_exists($configRepository, 'get')) {
                    $config = $configRepository->get('mail', []);
                }
            }

            // Provide default configuration if none exists
            if (empty($config)) {
                $config = $this->getDefaultConfig();
            }

            $mailer = new Mailer($config);

            // Set queue manager if available
            if ($container->has(QueueManager::class)) {
                $mailer->setQueueManager($container->get(QueueManager::class));
            }

            // Set logger if available
            if ($container->has('log')) {
                $mailer->setLogger($container->get('log'));
            }

            return $mailer;
        });

        // Alias for convenience
        $container->alias('mail', Mailer::class);
    }

    /**
     * @inheritDoc
     */
    public function provides(): array
    {
        return [
            Mailer::class,
            'mail',
        ];
    }

    /**
     * Get default mail configuration.
     *
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'default' => 'log',
            'from' => [
                'address' => 'hello@example.com',
                'name' => 'Lumina App',
            ],
            'mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => 'localhost',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => null,
                    'password' => null,
                    'timeout' => 30,
                ],
                'log' => [
                    'transport' => 'log',
                    'channel' => 'mail',
                ],
                'array' => [
                    'transport' => 'array',
                ],
            ],
        ];
    }
}
