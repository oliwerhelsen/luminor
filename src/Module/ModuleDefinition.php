<?php

declare(strict_types=1);

namespace Luminor\DDD\Module;

/**
 * Module configuration and metadata.
 *
 * Contains all the configuration needed to load and run a module.
 */
final class ModuleDefinition
{
    /**
     * @param string $name Unique module name
     * @param string $version Module version
     * @param string $description Module description
     * @param string $namespace Root namespace for the module
     * @param string $path Root path for the module files
     * @param array<int, string> $dependencies Module dependencies
     * @param array<string, mixed> $config Module configuration
     * @param bool $enabled Whether the module is enabled
     */
    public function __construct(
        private readonly string $name,
        private readonly string $version = '1.0.0',
        private readonly string $description = '',
        private readonly string $namespace = '',
        private readonly string $path = '',
        private readonly array $dependencies = [],
        private readonly array $config = [],
        private readonly bool $enabled = true
    ) {
    }

    /**
     * Create a module definition from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? throw new \InvalidArgumentException('Module name is required'),
            version: $data['version'] ?? '1.0.0',
            description: $data['description'] ?? '',
            namespace: $data['namespace'] ?? '',
            path: $data['path'] ?? '',
            dependencies: $data['dependencies'] ?? [],
            config: $data['config'] ?? [],
            enabled: $data['enabled'] ?? true
        );
    }

    /**
     * Get the module name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the module version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the module description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the module namespace.
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Get the module path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the module dependencies.
     *
     * @return array<int, string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Get the module configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get a specific configuration value.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Check if the module is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Create a new definition with different configuration.
     *
     * @param array<string, mixed> $config
     */
    public function withConfig(array $config): self
    {
        return new self(
            $this->name,
            $this->version,
            $this->description,
            $this->namespace,
            $this->path,
            $this->dependencies,
            array_merge($this->config, $config),
            $this->enabled
        );
    }

    /**
     * Create a new definition with enabled/disabled state.
     */
    public function withEnabled(bool $enabled): self
    {
        return new self(
            $this->name,
            $this->version,
            $this->description,
            $this->namespace,
            $this->path,
            $this->dependencies,
            $this->config,
            $enabled
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'namespace' => $this->namespace,
            'path' => $this->path,
            'dependencies' => $this->dependencies,
            'config' => $this->config,
            'enabled' => $this->enabled,
        ];
    }
}
