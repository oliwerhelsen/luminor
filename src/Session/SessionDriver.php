<?php

declare(strict_types=1);

namespace Luminor\Session;

/**
 * Session Driver Interface
 *
 * Defines how session data is persisted.
 */
interface SessionDriver
{
    /**
     * Read session data.
     *
     * @return array<string, mixed>
     */
    public function read(string $sessionId): array;

    /**
     * Write session data.
     *
     * @param array<string, mixed> $data
     */
    public function write(string $sessionId, array $data): bool;

    /**
     * Destroy a session.
     */
    public function destroy(string $sessionId): bool;

    /**
     * Garbage collection - remove old sessions.
     */
    public function gc(int $maxLifetime): int;
}
