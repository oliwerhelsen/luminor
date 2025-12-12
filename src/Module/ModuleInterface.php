<?php

declare(strict_types=1);

namespace Lumina\DDD\Module;

use Lumina\DDD\Container\ContainerInterface;

/**
 * Interface for application modules.
 *
 * Modules are self-contained units of functionality that can be
 * loaded and configured independently. They encapsulate domain logic,
 * services, and infrastructure for a specific feature or bounded context.
 */
interface ModuleInterface
{
    /**
     * Get the unique module name.
     *
     * This name is used to identify the module and should be unique
     * across all loaded modules.
     */
    public function getName(): string;

    /**
     * Get the module version.
     */
    public function getVersion(): string;

    /**
     * Get module dependencies.
     *
     * Returns an array of module names that must be loaded before this module.
     *
     * @return array<int, string>
     */
    public function getDependencies(): array;

    /**
     * Register module services in the container.
     *
     * This method is called during the boot phase to register
     * the module's services, repositories, and other dependencies.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Boot the module.
     *
     * This method is called after all modules have been registered.
     * Use it for initialization that requires other modules to be available.
     */
    public function boot(ContainerInterface $container): void;

    /**
     * Get the module's service provider class.
     *
     * @return class-string<ModuleServiceProvider>|null
     */
    public function getServiceProvider(): ?string;

    /**
     * Get the module's configuration.
     */
    public function getDefinition(): ModuleDefinition;
}
