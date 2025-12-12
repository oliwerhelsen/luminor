<?php

declare(strict_types=1);

namespace Lumina\DDD\Container;

/**
 * Interface for service providers.
 *
 * Service providers are responsible for registering bindings
 * and bootstrapping services in the container.
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings in the container.
     *
     * This method is called when the provider is registered.
     * Use it to bind interfaces to implementations.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Bootstrap any application services.
     *
     * This method is called after all providers have been registered.
     * Use it to perform any actions that require other services.
     */
    public function boot(ContainerInterface $container): void;

    /**
     * Get the services provided by this provider.
     *
     * If the provider is deferred, this method should return
     * an array of service names that this provider provides.
     *
     * @return array<string>
     */
    public function provides(): array;

    /**
     * Determine if the provider is deferred.
     *
     * Deferred providers are only loaded when one of their
     * services is actually needed.
     */
    public function isDeferred(): bool;
}
