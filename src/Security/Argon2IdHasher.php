<?php

declare(strict_types=1);

namespace Luminor\Security;

/**
 * Argon2id Password Hasher
 *
 * Uses PHP's Argon2id algorithm for password hashing (recommended).
 */
final class Argon2IdHasher implements Hasher
{
    private int $memory;
    private int $time;
    private int $threads;

    /**
     * @param int $memory Memory cost in KiB (default 65536 = 64MB)
     * @param int $time Time cost (iterations, default 4)
     * @param int $threads Parallelism factor (default 1)
     */
    public function __construct(
        int $memory = 65536,
        int $time = 4,
        int $threads = 1
    ) {
        $this->memory = $memory;
        $this->time = $time;
        $this->threads = $threads;
    }

    /**
     * @inheritDoc
     */
    public function make(string $value): string
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new \RuntimeException('Argon2id is not supported on this system.');
        }

        $hash = password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memory,
            'time_cost' => $this->time,
            'threads' => $this->threads,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Argon2id hashing failed.');
        }

        return $hash;
    }

    /**
     * @inheritDoc
     */
    public function check(string $value, string $hashedValue): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * @inheritDoc
     */
    public function needsRehash(string $hashedValue): bool
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            return false;
        }

        return password_needs_rehash($hashedValue, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memory,
            'time_cost' => $this->time,
            'threads' => $this->threads,
        ]);
    }
}
