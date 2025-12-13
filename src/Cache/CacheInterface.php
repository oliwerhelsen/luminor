<?php

declare(strict_types=1);

namespace Luminor\DDD\Cache;

/**
 * Cache Interface
 *
 * PSR-16 Simple Cache compatible interface.
 */
interface CacheInterface
{
    /**
     * Fetch a value from the cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the cache.
     *
     * @param int|null $ttl Time to live in seconds (null = forever)
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Determine if an item exists in the cache.
     */
    public function has(string $key): bool;

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool;

    /**
     * Get multiple values from the cache.
     *
     * @param array<string> $keys
     *
     * @return array<string, mixed>
     */
    public function many(array $keys): array;

    /**
     * Store multiple values in the cache.
     *
     * @param array<string, mixed> $values
     */
    public function putMany(array $values, ?int $ttl = null): bool;

    /**
     * Increment a value in the cache.
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Decrement a value in the cache.
     */
    public function decrement(string $key, int $value = 1): int|false;

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Get an item from the cache, or execute the callback and store the result.
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed;
}
