<?php

declare(strict_types=1);

namespace Luminor\DDD\Cache;

use Luminor\DDD\Cache\Drivers\FileCache;
use Luminor\DDD\Cache\Drivers\ArrayCache;

/**
 * Cache Manager
 *
 * Manages cache drivers and provides a unified interface.
 */
final class CacheManager implements CacheInterface
{
    private CacheInterface $driver;
    /** @var array<string, CacheInterface> */
    private array $drivers = [];
    private string $defaultDriver = 'file';

    public function __construct(?CacheInterface $driver = null)
    {
        $this->driver = $driver ?? new FileCache(sys_get_temp_dir() . '/luminor_cache');
    }

    /**
     * Create a file-based cache.
     */
    public static function file(string $path): self
    {
        return new self(new FileCache($path));
    }

    /**
     * Create an array-based cache (for testing).
     */
    public static function array(): self
    {
        return new self(new ArrayCache());
    }

    /**
     * Set the default driver.
     */
    public function setDefaultDriver(string $driver): self
    {
        if (!isset($this->drivers[$driver])) {
            throw new \InvalidArgumentException("Cache driver [{$driver}] not found.");
        }

        $this->defaultDriver = $driver;
        $this->driver = $this->drivers[$driver];
        return $this;
    }

    /**
     * Get a specific driver.
     */
    public function driver(string $name): CacheInterface
    {
        if (!isset($this->drivers[$name])) {
            throw new \InvalidArgumentException("Cache driver [{$name}] not found.");
        }

        return $this->drivers[$name];
    }

    /**
     * Register a custom driver.
     */
    public function extend(string $name, CacheInterface $driver): self
    {
        $this->drivers[$name] = $driver;
        return $this;
    }

    // Forward all calls to the active driver

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->driver->put($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    public function forget(string $key): bool
    {
        return $this->driver->forget($key);
    }

    public function flush(): bool
    {
        return $this->driver->flush();
    }

    public function many(array $keys): array
    {
        return $this->driver->many($keys);
    }

    public function putMany(array $values, ?int $ttl = null): bool
    {
        return $this->driver->putMany($values, $ttl);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->driver->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->driver->decrement($key, $value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->driver->forever($key, $value);
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        return $this->driver->remember($key, $ttl, $callback);
    }
}
