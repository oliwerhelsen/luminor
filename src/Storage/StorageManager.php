<?php

declare(strict_types=1);

namespace Luminor\DDD\Storage;

use Luminor\DDD\Storage\Drivers\LocalStorage;

/**
 * Storage Manager
 *
 * Manages storage disks and provides a unified interface.
 */
final class StorageManager implements StorageInterface
{
    private StorageInterface $driver;
    /** @var array<string, StorageInterface> */
    private array $disks = [];
    private string $defaultDisk = 'local';

    public function __construct(?StorageInterface $driver = null)
    {
        $this->driver = $driver ?? new LocalStorage(getcwd() . '/storage');
    }

    /**
     * Create a local storage instance.
     */
    public static function local(string $root, string $urlBase = '/storage'): self
    {
        return new self(new LocalStorage($root, $urlBase));
    }

    /**
     * Get a specific disk.
     */
    public function disk(string $name): StorageInterface
    {
        if (!isset($this->disks[$name])) {
            throw new \InvalidArgumentException("Storage disk [{$name}] not found.");
        }

        return $this->disks[$name];
    }

    /**
     * Register a disk.
     */
    public function extend(string $name, StorageInterface $disk): self
    {
        $this->disks[$name] = $disk;
        return $this;
    }

    /**
     * Set the default disk.
     */
    public function setDefaultDisk(string $disk): self
    {
        if (!isset($this->disks[$disk])) {
            throw new \InvalidArgumentException("Storage disk [{$disk}] not found.");
        }

        $this->defaultDisk = $disk;
        $this->driver = $this->disks[$disk];
        return $this;
    }

    // Forward all calls to the active driver

    public function put(string $path, string|resource $contents): bool
    {
        return $this->driver->put($path, $contents);
    }

    public function get(string $path): string|false
    {
        return $this->driver->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->driver->exists($path);
    }

    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }

    public function copy(string $from, string $to): bool
    {
        return $this->driver->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->driver->move($from, $to);
    }

    public function size(string $path): int|false
    {
        return $this->driver->size($path);
    }

    public function lastModified(string $path): int|false
    {
        return $this->driver->lastModified($path);
    }

    public function files(string $directory = ''): array
    {
        return $this->driver->files($directory);
    }

    public function directories(string $directory = ''): array
    {
        return $this->driver->directories($directory);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->driver->makeDirectory($path);
    }

    public function deleteDirectory(string $directory): bool
    {
        return $this->driver->deleteDirectory($directory);
    }

    public function url(string $path): string
    {
        return $this->driver->url($path);
    }
}
