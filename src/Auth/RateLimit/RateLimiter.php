<?php

declare(strict_types=1);

namespace Luminor\Auth\RateLimit;

/**
 * Rate Limiter using token bucket algorithm
 */
class RateLimiter
{
    private RateLimitStore $store;
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(RateLimitStore $store, int $maxAttempts = 5, int $decaySeconds = 60)
    {
        $this->store = $store;
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    /**
     * Attempt to perform an action
     *
     * @param string $key Unique identifier (e.g., IP address, user ID)
     * @param int|null $maxAttempts Override default max attempts
     * @return bool True if allowed, false if rate limited
     */
    public function attempt(string $key, ?int $maxAttempts = null): bool
    {
        $maxAttempts = $maxAttempts ?? $this->maxAttempts;

        $attempts = $this->store->get($key);

        if ($attempts === null) {
            $this->store->set($key, 1, $this->decaySeconds);
            return true;
        }

        if ($attempts >= $maxAttempts) {
            return false;
        }

        $this->store->increment($key);
        return true;
    }

    /**
     * Check if key is rate limited without incrementing
     *
     * @param string $key
     * @param int|null $maxAttempts
     * @return bool
     */
    public function tooManyAttempts(string $key, ?int $maxAttempts = null): bool
    {
        $maxAttempts = $maxAttempts ?? $this->maxAttempts;
        $attempts = $this->store->get($key);

        return $attempts !== null && $attempts >= $maxAttempts;
    }

    /**
     * Get number of attempts for a key
     *
     * @param string $key
     * @return int
     */
    public function attempts(string $key): int
    {
        return $this->store->get($key) ?? 0;
    }

    /**
     * Get number of remaining attempts
     *
     * @param string $key
     * @param int|null $maxAttempts
     * @return int
     */
    public function remaining(string $key, ?int $maxAttempts = null): int
    {
        $maxAttempts = $maxAttempts ?? $this->maxAttempts;
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Get seconds until key is reset
     *
     * @param string $key
     * @return int
     */
    public function availableIn(string $key): int
    {
        return $this->store->ttl($key);
    }

    /**
     * Clear attempts for a key
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void
    {
        $this->store->delete($key);
    }

    /**
     * Reset rate limiter for a key (allows immediate retry)
     *
     * @param string $key
     * @return void
     */
    public function reset(string $key): void
    {
        $this->clear($key);
    }

    /**
     * Hit the rate limiter (increment attempts)
     *
     * @param string $key
     * @param int $decaySeconds
     * @return int Current attempts
     */
    public function hit(string $key, ?int $decaySeconds = null): int
    {
        $decaySeconds = $decaySeconds ?? $this->decaySeconds;

        $attempts = $this->store->get($key);

        if ($attempts === null) {
            $this->store->set($key, 1, $decaySeconds);
            return 1;
        }

        return $this->store->increment($key);
    }
}
