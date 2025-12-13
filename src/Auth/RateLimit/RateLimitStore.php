<?php

declare(strict_types=1);

namespace Luminor\Auth\RateLimit;

/**
 * Rate Limit Storage Interface
 */
interface RateLimitStore
{
    /**
     * Get the value for a key
     *
     * @param string $key
     * @return int|null
     */
    public function get(string $key): ?int;

    /**
     * Set a value with TTL
     *
     * @param string $key
     * @param int $value
     * @param int $ttlSeconds
     * @return void
     */
    public function set(string $key, int $value, int $ttlSeconds): void;

    /**
     * Increment a key's value
     *
     * @param string $key
     * @return int New value
     */
    public function increment(string $key): int;

    /**
     * Get TTL for a key
     *
     * @param string $key
     * @return int Seconds remaining, 0 if expired/not found
     */
    public function ttl(string $key): int;

    /**
     * Delete a key
     *
     * @param string $key
     * @return void
     */
    public function delete(string $key): void;
}
