<?php

declare(strict_types=1);

namespace Lumina\DDD\Storage;

/**
 * Storage Interface
 *
 * Defines the contract for file storage operations.
 */
interface StorageInterface
{
    /**
     * Store a file.
     */
    public function put(string $path, string|resource $contents): bool;

    /**
     * Get the contents of a file.
     */
    public function get(string $path): string|false;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Delete a file.
     */
    public function delete(string $path): bool;

    /**
     * Copy a file.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file.
     */
    public function move(string $from, string $to): bool;

    /**
     * Get the size of a file.
     */
    public function size(string $path): int|false;

    /**
     * Get the last modified time of a file.
     */
    public function lastModified(string $path): int|false;

    /**
     * Get all files in a directory.
     *
     * @return array<string>
     */
    public function files(string $directory = ''): array;

    /**
     * Get all directories within a directory.
     *
     * @return array<string>
     */
    public function directories(string $directory = ''): array;

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path): bool;

    /**
     * Delete a directory.
     */
    public function deleteDirectory(string $directory): bool;

    /**
     * Get the URL for a file.
     */
    public function url(string $path): string;
}
