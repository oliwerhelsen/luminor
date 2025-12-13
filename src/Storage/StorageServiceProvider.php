<?php

declare(strict_types=1);

namespace Luminor\DDD\Storage;

use Luminor\DDD\Container\AbstractServiceProvider;

/**
 * Storage Service Provider
 *
 * Registers storage services in the container.
 */
final class StorageServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->container->singleton(StorageManager::class, function () {
            $root = getenv('STORAGE_PATH') ?: getcwd() . '/storage';
            $urlBase = getenv('STORAGE_URL') ?: '/storage';

            return StorageManager::local($root, $urlBase);
        });

        $this->container->alias(StorageManager::class, StorageInterface::class);
        $this->container->alias(StorageManager::class, 'storage');
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        // Boot logic if needed
    }
}
