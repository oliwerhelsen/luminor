<?php

declare(strict_types=1);

namespace Luminor\DDD\Cache\Drivers;

use Luminor\DDD\Cache\CacheInterface;

/**
 * File Cache Driver
 *
 * Stores cache data in files.
 */
final class FileCache implements CacheInterface
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);

        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return $default;
        }

        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            $this->forget($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * @inheritDoc
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $expires = $ttl === null ? 0 : time() + $ttl;

        $data = serialize([
            'value' => $value,
            'expires' => $expires,
        ]);

        return file_put_contents($file, $data, LOCK_EX) !== false;
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
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush(): bool
    {
        $files = glob($this->path . '/cache_*');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            unlink($file);
        }

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
            if (!$this->put($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $current = $this->get($key, 0);

        if (!is_numeric($current)) {
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
     * Get the file path for a cache key.
     */
    private function getFilePath(string $key): string
    {
        return $this->path . '/cache_' . md5($key);
    }
}
