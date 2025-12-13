<?php

declare(strict_types=1);

namespace Lumina\DDD\Session;

use Lumina\DDD\Container\AbstractServiceProvider;

/**
 * Session Service Provider
 *
 * Registers session services in the container.
 */
final class SessionServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->container->singleton(SessionManager::class, function ($container) {
            $driver = getenv('SESSION_DRIVER') ?: 'file';
            $sessionName = getenv('SESSION_NAME') ?: 'lumina_session';

            return match ($driver) {
                'file' => SessionManager::file(
                    getenv('SESSION_PATH') ?: sys_get_temp_dir() . '/lumina_sessions',
                    $sessionName
                ),
                'array' => SessionManager::array($sessionName),
                'database' => SessionManager::database(
                    $container->make(\Lumina\DDD\Database\ConnectionInterface::class),
                    getenv('SESSION_TABLE') ?: 'sessions',
                    $sessionName
                ),
                default => SessionManager::file(sys_get_temp_dir() . '/lumina_sessions', $sessionName),
            };
        });

        $this->container->alias(SessionManager::class, 'session');
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        // Boot logic if needed
    }
}
