<?php

declare(strict_types=1);

namespace Luminor\DDD\Security;

use RuntimeException;

/**
 * Bcrypt Password Hasher
 *
 * Uses PHP's bcrypt algorithm for password hashing.
 */
final class BcryptHasher implements Hasher
{
    private int $rounds;

    /**
     * @param int $rounds Bcrypt cost factor (4-31, default 10)
     */
    public function __construct(int $rounds = 10)
    {
        $this->rounds = max(4, min(31, $rounds));
    }

    /**
     * @inheritDoc
     */
    public function make(string $value): string
    {
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->rounds,
        ]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing failed.');
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
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->rounds,
        ]);
    }

    /**
     * Set the cost factor.
     */
    public function setRounds(int $rounds): self
    {
        $this->rounds = max(4, min(31, $rounds));

        return $this;
    }
}
