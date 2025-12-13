<?php

declare(strict_types=1);

namespace Luminor\DDD\Observability;

use Luminor\DDD\Container\AbstractServiceProvider;
use Luminor\DDD\Container\ContainerInterface;

/**
 * Service provider for observability features.
 */
final class ObservabilityServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MetricsInterface::class, function (ContainerInterface $container) {
            $config = $container->get('config');
            $driver = $config->get('observability.metrics.driver', 'memory');

            return match ($driver) {
                'memory' => new InMemoryMetrics(),
                'null' => new NullMetrics(),
                default => throw new \InvalidArgumentException("Unsupported metrics driver: {$driver}"),
            };
        });
    }

    public function boot(ContainerInterface $container): void
    {
        // Boot logic if needed
    }
}
