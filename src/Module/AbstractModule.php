<?php

declare(strict_types=1);

namespace Luminor\DDD\Module;

use Luminor\DDD\Container\ContainerInterface;

/**
 * Abstract base class for modules.
 *
 * Provides a convenient base implementation of ModuleInterface
 * with sensible defaults.
 */
abstract class AbstractModule implements ModuleInterface
{
    protected ModuleDefinition $definition;

    public function __construct()
    {
        $this->definition = $this->configure();
    }

    /**
     * Configure the module definition.
     *
     * Override this method to set module metadata.
     */
    protected function configure(): ModuleDefinition
    {
        return new ModuleDefinition(
            name: $this->getName(),
            version: $this->getVersion(),
            description: $this->getDescription(),
            namespace: $this->getNamespace(),
            path: $this->getPath()
        );
    }

    /**
     * @inheritDoc
     */
    abstract public function getName(): string;

    /**
     * @inheritDoc
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get the module description.
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * Get the module namespace.
     */
    public function getNamespace(): string
    {
        return '';
    }

    /**
     * Get the module path.
     */
    public function getPath(): string
    {
        $reflection = new \ReflectionClass($this);
        return dirname($reflection->getFileName());
    }

    /**
     * @inheritDoc
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function register(ContainerInterface $container): void
    {
        $providerClass = $this->getServiceProvider();

        if ($providerClass === null) {
            return;
        }

        $provider = new $providerClass();
        $provider->setContainer($container);
        $provider->setDefinition($this->definition);
        $provider->register();
    }

    /**
     * @inheritDoc
     */
    public function boot(ContainerInterface $container): void
    {
        $providerClass = $this->getServiceProvider();

        if ($providerClass === null) {
            return;
        }

        $provider = new $providerClass();
        $provider->setContainer($container);
        $provider->setDefinition($this->definition);
        $provider->boot();
    }

    /**
     * @inheritDoc
     */
    public function getServiceProvider(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getDefinition(): ModuleDefinition
    {
        return $this->definition;
    }

    /**
     * Get a configuration value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->definition->getConfigValue($key, $default);
    }
}
