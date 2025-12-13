<?php

declare(strict_types=1);

namespace Luminor\DDD\Session;

/**
 * Session Interface
 *
 * Defines the contract for session storage drivers.
 */
interface SessionInterface
{
    /**
     * Start the session.
     */
    public function start(): bool;

    /**
     * Save the session data.
     */
    public function save(): void;

    /**
     * Get a value from the session.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Put a value in the session.
     */
    public function put(string $key, mixed $value): void;

    /**
     * Check if a key exists in the session.
     */
    public function has(string $key): bool;

    /**
     * Remove a key from the session.
     */
    public function forget(string $key): void;

    /**
     * Remove all session data.
     */
    public function flush(): void;

    /**
     * Regenerate the session ID.
     */
    public function regenerate(bool $destroy = false): bool;

    /**
     * Get the session ID.
     */
    public function getId(): string;

    /**
     * Set the session ID.
     */
    public function setId(string $id): void;

    /**
     * Get all session data.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Destroy the session.
     */
    public function destroy(): bool;

    /**
     * Check if the session has been started.
     */
    public function isStarted(): bool;
}
