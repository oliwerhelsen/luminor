<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Events;

use Luminor\DDD\Container\AbstractServiceProvider;
use Luminor\DDD\Container\ContainerInterface;
use Luminor\DDD\Database\ConnectionInterface;
use Luminor\DDD\Infrastructure\Persistence\DatabaseEventStore;
use Luminor\DDD\Infrastructure\Persistence\InMemoryEventStore;

/**
 * Service provider for the event store.
 */
final class EventStoreServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(EventStoreInterface::class, function (ContainerInterface $container) {
            $config = $container->get('config');
            $driver = $config->get('events.store.driver', 'database');

            return match ($driver) {
                'database' => new DatabaseEventStore(
                    $container->get(ConnectionInterface::class)
                ),
                'memory' => new InMemoryEventStore(),
                default => throw new \InvalidArgumentException("Unsupported event store driver: {$driver}"),
            };
        });
    }

    public function boot(ContainerInterface $container): void
    {
        // Boot logic if needed
    }
}
