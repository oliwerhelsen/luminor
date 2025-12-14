<?php

declare(strict_types=1);

namespace Luminor\Security;

/**
 * Password Hasher Interface
 *
 * Provides password hashing and verification functionality.
 */
interface Hasher
{
    /**
     * Hash a password.
     *
     * @param string $value Plain text password
     * @return string Hashed password
     */
    public function make(string $value): string;

    /**
     * Verify a password against a hash.
     *
     * @param string $value Plain text password
     * @param string $hashedValue Hashed password
     * @return bool True if password matches
     */
    public function check(string $value, string $hashedValue): bool;

    /**
     * Check if a hash needs to be rehashed.
     *
     * @param string $hashedValue Hashed password
     * @return bool True if needs rehashing
     */
    public function needsRehash(string $hashedValue): bool;
}
