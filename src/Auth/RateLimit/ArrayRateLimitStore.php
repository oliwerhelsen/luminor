<?php

declare(strict_types=1);

namespace Luminor\Auth\RateLimit;

/**
 * In-memory rate limit store (for testing or single-process applications)
 */
class ArrayRateLimitStore implements RateLimitStore
{
    private array $data = [];

    public function get(string $key): ?int
    {
        if (!isset($this->data[$key])) {
            return null;
        }

        [$value, $expiresAt] = $this->data[$key];

        if (time() >= $expiresAt) {
            unset($this->data[$key]);
            return null;
        }

        return $value;
    }

    public function set(string $key, int $value, int $ttlSeconds): void
    {
        $this->data[$key] = [$value, time() + $ttlSeconds];
    }

    public function increment(string $key): int
    {
        $current = $this->get($key);

        if ($current === null) {
            return 0;
        }

        $newValue = $current + 1;
        [$, $expiresAt] = $this->data[$key];
        $this->data[$key] = [$newValue, $expiresAt];

        return $newValue;
    }

    public function ttl(string $key): int
    {
        if (!isset($this->data[$key])) {
            return 0;
        }

        [$, $expiresAt] = $this->data[$key];
        return max(0, $expiresAt - time());
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
    }
}
