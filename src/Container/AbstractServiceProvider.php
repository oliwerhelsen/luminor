<?php

declare(strict_types=1);

namespace Lumina\DDD\Container;

/**
 * Abstract base class for service providers.
 *
 * Provides default implementations for common provider methods.
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    abstract public function register(ContainerInterface $container): void;

    /**
     * @inheritDoc
     */
    public function boot(ContainerInterface $container): void
    {
        // Default implementation does nothing
    }

    /**
     * @inheritDoc
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function isDeferred(): bool
    {
        return false;
    }
}
