<?php

declare(strict_types=1);

namespace Luminor\Module;

use Luminor\Container\ContainerInterface;

/**
 * Base class for module service providers.
 *
 * Service providers are responsible for registering a module's
 * services, repositories, event handlers, and other dependencies
 * with the application container.
 */
abstract class ModuleServiceProvider
{
    protected ContainerInterface $container;
    protected ModuleDefinition $definition;

    /**
     * Set the container instance.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Set the module definition.
     */
    public function setDefinition(ModuleDefinition $definition): void
    {
        $this->definition = $definition;
    }

    /**
     * Get a configuration value from the module definition.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->definition->getConfigValue($key, $default);
    }

    /**
     * Register services with the container.
     *
     * This method is called during the registration phase.
     * Use it to bind interfaces to implementations.
     */
    abstract public function register(): void;

    /**
     * Boot the service provider.
     *
     * This method is called after all providers have been registered.
     * Use it for initialization that requires other services.
     */
    public function boot(): void
    {
        // Override in subclass if needed
    }

    /**
     * Get the services provided by this provider.
     *
     * Return an array of service names/interfaces that this provider registers.
     * This can be used for lazy loading.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Determine if the provider should be deferred.
     *
     * Deferred providers are only loaded when one of their services is requested.
     */
    public function isDeferred(): bool
    {
        return false;
    }

    /**
     * Register a singleton service.
     *
     * @param class-string $abstract
     * @param callable|class-string|null $concrete
     */
    protected function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete ?? $abstract);
    }

    /**
     * Register a binding.
     *
     * @param class-string $abstract
     * @param callable|class-string|null $concrete
     */
    protected function bind(string $abstract, callable|string|null $concrete = null): void
    {
        $this->container->bind($abstract, $concrete ?? $abstract);
    }

    /**
     * Register an instance.
     *
     * @param class-string $abstract
     */
    protected function instance(string $abstract, object $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * Register a factory.
     *
     * @param class-string $abstract
     */
    protected function factory(string $abstract, callable $factory): void
    {
        $this->container->bind($abstract, $factory);
    }
}
