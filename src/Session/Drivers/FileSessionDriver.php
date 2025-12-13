<?php

declare(strict_types=1);

namespace Lumina\DDD\Session\Drivers;

use Lumina\DDD\Session\SessionDriver;

/**
 * File Session Driver
 *
 * Stores session data in files.
 */
final class FileSessionDriver implements SessionDriver
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * @inheritDoc
     */
    public function read(string $sessionId): array
    {
        $file = $this->getFilePath($sessionId);

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return [];
        }

        $data = unserialize($content);

        return is_array($data) ? $data : [];
    }

    /**
     * @inheritDoc
     */
    public function write(string $sessionId, array $data): bool
    {
        $file = $this->getFilePath($sessionId);
        $content = serialize($data);

        return file_put_contents($file, $content, LOCK_EX) !== false;
    }

    /**
     * @inheritDoc
     */
    public function destroy(string $sessionId): bool
    {
        $file = $this->getFilePath($sessionId);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function gc(int $maxLifetime): int
    {
        $files = glob($this->path . '/sess_*');

        if ($files === false) {
            return 0;
        }

        $now = time();
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) + $maxLifetime < $now && unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get the file path for a session ID.
     */
    private function getFilePath(string $sessionId): string
    {
        return $this->path . '/sess_' . $sessionId;
    }
}
