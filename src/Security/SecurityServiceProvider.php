<?php

declare(strict_types=1);

namespace Luminor\Security;

use Luminor\Container\AbstractServiceProvider;

/**
 * Security Service Provider
 *
 * Registers security services in the container.
 */
final class SecurityServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        // Register hash manager
        $this->container->singleton(HashManager::class, function () {
            $manager = new HashManager();

            // Configure default driver from config
            $driver = getenv('HASH_DRIVER') ?: 'argon2id';
            if (in_array($driver, ['bcrypt', 'argon2id'])) {
                try {
                    $manager->setDefaultDriver($driver);
                } catch (\InvalidArgumentException $e) {
                    // Fall back to bcrypt if argon2id not available
                    $manager->setDefaultDriver('bcrypt');
                }
            }

            return $manager;
        });

        // Alias for easier access
        $this->container->alias(HashManager::class, Hasher::class);
        $this->container->alias(HashManager::class, 'hash');
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        // Boot logic if needed
    }
}
