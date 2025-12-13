<?php

declare(strict_types=1);

namespace Luminor\DDD\Storage\Drivers;

use Luminor\DDD\Storage\StorageInterface;

/**
 * Local Storage Driver
 *
 * Stores files on the local filesystem.
 */
final class LocalStorage implements StorageInterface
{
    private string $root;
    private string $urlBase;

    public function __construct(string $root, string $urlBase = '/storage')
    {
        $this->root = rtrim($root, '/');
        $this->urlBase = rtrim($urlBase, '/');

        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, string|resource $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (is_resource($contents)) {
            $file = fopen($fullPath, 'w');
            if ($file === false) {
                return false;
            }

            stream_copy_to_stream($contents, $file);
            fclose($file);
            return true;
        }

        return file_put_contents($fullPath, $contents) !== false;
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): string|false
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return file_get_contents($fullPath);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    /**
     * @inheritDoc
     */
    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return copy($fromPath, $toPath);
    }

    /**
     * @inheritDoc
     */
    public function move(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return rename($fromPath, $toPath);
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): int|false
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return filesize($fullPath);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): int|false
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return filemtime($fullPath);
    }

    /**
     * @inheritDoc
     */
    public function files(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        $items = scandir($fullPath);

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;

            if (is_file($itemPath)) {
                $files[] = $directory ? $directory . '/' . $item : $item;
            }
        }

        return $files;
    }

    /**
     * @inheritDoc
     */
    public function directories(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];
        $items = scandir($fullPath);

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;

            if (is_dir($itemPath)) {
                $directories[] = $directory ? $directory . '/' . $item : $item;
            }
        }

        return $directories;
    }

    /**
     * @inheritDoc
     */
    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, 0755, true);
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $directory): bool
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return false;
        }

        return $this->removeDirectory($fullPath);
    }

    /**
     * @inheritDoc
     */
    public function url(string $path): string
    {
        return $this->urlBase . '/' . ltrim($path, '/');
    }

    /**
     * Get the full filesystem path.
     */
    private function getFullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectory(string $path): bool
    {
        $items = scandir($path);

        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        return rmdir($path);
    }
}
