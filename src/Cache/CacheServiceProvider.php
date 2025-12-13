<?php

declare(strict_types=1);

namespace Luminor\DDD\Cache;

use Luminor\DDD\Container\AbstractServiceProvider;

/**
 * Cache Service Provider
 *
 * Registers cache services in the container.
 */
final class CacheServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->container->singleton(CacheManager::class, function () {
            $driver = getenv('CACHE_DRIVER') ?: 'file';

            return match ($driver) {
                'file' => CacheManager::file(
                    getenv('CACHE_PATH') ?: sys_get_temp_dir() . '/luminor_cache'
                ),
                'array' => CacheManager::array(),
                default => CacheManager::file(sys_get_temp_dir() . '/luminor_cache'),
            };
        });

        $this->container->alias(CacheManager::class, CacheInterface::class);
        $this->container->alias(CacheManager::class, 'cache');
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        // Boot logic if needed
    }
}
