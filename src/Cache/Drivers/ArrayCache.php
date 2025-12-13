<?php

declare(strict_types=1);

namespace Luminor\DDD\Cache\Drivers;

use Luminor\DDD\Cache\CacheInterface;

/**
 * Array Cache Driver
 *
 * Stores cache data in memory (useful for testing).
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: int}> */
    private array $cache = [];

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! isset($this->cache[$key])) {
            return $default;
        }

        $data = $this->cache[$key];

        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            unset($this->cache[$key]);

            return $default;
        }

        return $data['value'];
    }

    /**
     * @inheritDoc
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expires = $ttl === null ? 0 : time() + $ttl;

        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @inheritDoc
     */
    public function forget(string $key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush(): bool
    {
        $this->cache = [];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function many(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function putMany(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $current = $this->get($key, 0);

        if (! is_numeric($current)) {
            return false;
        }

        $new = (int) $current + $value;
        $this->put($key, $new);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    /**
     * @inheritDoc
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, null);
    }

    /**
     * @inheritDoc
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /**
     * Get all cache data (for testing).
     *
     * @return array<string, array{value: mixed, expires: int}>
     */
    public function all(): array
    {
        return $this->cache;
    }
}
