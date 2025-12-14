<?php

declare(strict_types=1);

namespace Luminor\Security;

/**
 * Hash Manager
 *
 * Manages password hashing drivers and provides a unified interface.
 */
final class HashManager implements Hasher
{
    private Hasher $driver;
    /** @var array<string, Hasher> */
    private array $drivers = [];
    private string $default = 'bcrypt';

    public function __construct(?Hasher $driver = null)
    {
        $this->driver = $driver ?? new BcryptHasher();
        $this->registerDefaultDrivers();
    }

    /**
     * Register default hashing drivers.
     */
    private function registerDefaultDrivers(): void
    {
        $this->drivers['bcrypt'] = new BcryptHasher();

        if (defined('PASSWORD_ARGON2ID')) {
            $this->drivers['argon2id'] = new Argon2IdHasher();
            $this->default = 'argon2id'; // Prefer Argon2id if available
        }
    }

    /**
     * Set the default driver.
     */
    public function setDefaultDriver(string $driver): self
    {
        if (!isset($this->drivers[$driver])) {
            throw new \InvalidArgumentException("Hash driver [{$driver}] not found.");
        }

        $this->default = $driver;
        $this->driver = $this->drivers[$driver];
        return $this;
    }

    /**
     * Get a specific driver.
     */
    public function driver(string $name): Hasher
    {
        if (!isset($this->drivers[$name])) {
            throw new \InvalidArgumentException("Hash driver [{$name}] not found.");
        }

        return $this->drivers[$name];
    }

    /**
     * Register a custom driver.
     */
    public function extend(string $name, Hasher $driver): self
    {
        $this->drivers[$name] = $driver;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make(string $value): string
    {
        return $this->driver->make($value);
    }

    /**
     * @inheritDoc
     */
    public function check(string $value, string $hashedValue): bool
    {
        return $this->driver->check($value, $hashedValue);
    }

    /**
     * @inheritDoc
     */
    public function needsRehash(string $hashedValue): bool
    {
        return $this->driver->needsRehash($hashedValue);
    }

    /**
     * Get information about a hash.
     *
     * @return array{algo: string|null, algoName: string, options: array<string, mixed>}
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }
}
