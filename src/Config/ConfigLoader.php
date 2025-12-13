<?php

declare(strict_types=1);

namespace Luminor\DDD\Config;

/**
 * Loads configuration files from a directory.
 *
 * Supports PHP configuration files that return arrays.
 */
final class ConfigLoader
{
    /**
     * @param string $configPath Base path for configuration files
     */
    public function __construct(
        private readonly string $configPath
    ) {
    }

    /**
     * Load all configuration files from the config directory.
     *
     * @return array<string, mixed>
     */
    public function loadAll(): array
    {
        $config = [];

        if (!is_dir($this->configPath)) {
            return $config;
        }

        $files = glob($this->configPath . '/*.php');

        if ($files === false) {
            return $config;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $config[$key] = $this->loadFile($file);
        }

        return $config;
    }

    /**
     * Load a specific configuration file.
     *
     * @param string $name The configuration file name (without .php extension)
     * @return array<string, mixed>
     */
    public function load(string $name): array
    {
        $file = $this->configPath . '/' . $name . '.php';

        if (!file_exists($file)) {
            return [];
        }

        return $this->loadFile($file);
    }

    /**
     * Load a configuration file.
     *
     * @return array<string, mixed>
     */
    private function loadFile(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $config = require $file;

        if (!is_array($config)) {
            return [];
        }

        return $config;
    }

    /**
     * Create a ConfigRepository with all loaded configuration.
     */
    public function loadInto(): ConfigRepository
    {
        return new ConfigRepository($this->loadAll());
    }

    /**
     * Get the configuration path.
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /**
     * Check if a configuration file exists.
     *
     * @param string $name The configuration file name (without .php extension)
     */
    public function exists(string $name): bool
    {
        return file_exists($this->configPath . '/' . $name . '.php');
    }

    /**
     * Get all available configuration file names.
     *
     * @return array<string>
     */
    public function getAvailableConfigs(): array
    {
        if (!is_dir($this->configPath)) {
            return [];
        }

        $files = glob($this->configPath . '/*.php');

        if ($files === false) {
            return [];
        }

        return array_map(fn($file) => basename($file, '.php'), $files);
    }
}
