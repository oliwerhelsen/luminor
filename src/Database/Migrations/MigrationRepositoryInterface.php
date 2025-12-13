<?php

declare(strict_types=1);

namespace Lumina\DDD\Database\Migrations;

/**
 * Migration Repository Interface
 *
 * Manages the migration history, tracking which migrations
 * have been executed.
 */
interface MigrationRepositoryInterface
{
    /**
     * Get all executed migrations.
     *
     * @return array<string> Array of migration names
     */
    public function getMigrations(): array;

    /**
     * Log that a migration was executed.
     *
     * @param string $name The migration name
     * @param int $batch The batch number
     */
    public function log(string $name, int $batch): void;

    /**
     * Remove a migration from the log.
     *
     * @param string $name The migration name
     */
    public function delete(string $name): void;

    /**
     * Get the last batch number.
     *
     * @return int The batch number
     */
    public function getLastBatchNumber(): int;

    /**
     * Get migrations from a specific batch.
     *
     * @param int $batch The batch number
     * @return array<string> Array of migration names
     */
    public function getMigrationsByBatch(int $batch): array;

    /**
     * Create the migration repository table.
     */
    public function createRepository(): void;

    /**
     * Check if the migration repository exists.
     *
     * @return bool True if the repository exists
     */
    public function repositoryExists(): bool;
}
