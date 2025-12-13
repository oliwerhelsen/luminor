<?php

declare(strict_types=1);

namespace Lumina\DDD\Session\Drivers;

use Lumina\DDD\Session\SessionDriver;

/**
 * Array Session Driver
 *
 * Stores session data in memory (useful for testing).
 */
final class ArraySessionDriver implements SessionDriver
{
    /** @var array<string, array<string, mixed>> */
    private array $sessions = [];

    /**
     * @inheritDoc
     */
    public function read(string $sessionId): array
    {
        return $this->sessions[$sessionId] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function write(string $sessionId, array $data): bool
    {
        $this->sessions[$sessionId] = $data;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function destroy(string $sessionId): bool
    {
        unset($this->sessions[$sessionId]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function gc(int $maxLifetime): int
    {
        // Memory-based driver doesn't need GC
        return 0;
    }

    /**
     * Get all sessions (for testing).
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->sessions;
    }

    /**
     * Clear all sessions (for testing).
     */
    public function clear(): void
    {
        $this->sessions = [];
    }
}
