<?php

declare(strict_types=1);

namespace Lumina\DDD\Config;

/**
 * Configuration repository for storing and retrieving configuration values.
 *
 * Supports dot notation for accessing nested values (e.g., "database.host").
 */
final class ConfigRepository
{
    /** @var array<string, mixed> */
    private array $items = [];

    /**
     * @param array<string, mixed> $items Initial configuration items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key The configuration key (supports dot notation)
     * @param mixed $default Default value if key doesn't exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value.
     *
     * @param string $key The configuration key (supports dot notation)
     * @param mixed $value The value to set
     */
    public function set(string $key, mixed $value): void
    {
        if (!str_contains($key, '.')) {
            $this->items[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key The configuration key (supports dot notation)
     */
    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->items)) {
            return true;
        }

        if (!str_contains($key, '.')) {
            return false;
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Get all configuration items.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Merge configuration items.
     *
     * @param array<string, mixed> $items Items to merge
     */
    public function merge(array $items): void
    {
        $this->items = array_merge_recursive($this->items, $items);
    }

    /**
     * Set multiple configuration items at once.
     *
     * @param array<string, mixed> $items Items to set (replaces existing)
     */
    public function setMany(array $items): void
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Remove a configuration item.
     *
     * @param string $key The configuration key
     */
    public function forget(string $key): void
    {
        if (!str_contains($key, '.')) {
            unset($this->items[$key]);
            return;
        }

        $keys = explode('.', $key);
        $current = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                unset($current[$segment]);
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    return;
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Push a value onto an array configuration value.
     *
     * @param string $key The configuration key
     * @param mixed $value The value to push
     */
    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);

        if (!is_array($array)) {
            $array = [];
        }

        $array[] = $value;

        $this->set($key, $array);
    }

    /**
     * Get the configuration as a nested array section.
     *
     * @param string $key The section key
     * @return array<string, mixed>
     */
    public function getSection(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? $value : [];
    }
}
