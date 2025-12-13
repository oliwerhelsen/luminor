<?php

declare(strict_types=1);

namespace Luminor\DDD\Database\Migrations;

use Luminor\DDD\Database\Schema\Schema;
use Luminor\DDD\Container\ContainerInterface;

/**
 * Migrator
 *
 * Handles running and rolling back migrations.
 */
final class Migrator
{
    private MigrationRepositoryInterface $repository;
    private Schema $schema;
    private ContainerInterface $container;
    /** @var array<string> */
    private array $paths;

    /**
     * @param array<string> $paths Paths to migration directories
     */
    public function __construct(
        MigrationRepositoryInterface $repository,
        Schema $schema,
        ContainerInterface $container,
        array $paths = []
    ) {
        $this->repository = $repository;
        $this->schema = $schema;
        $this->container = $container;
        $this->paths = $paths;
    }

    /**
     * Run pending migrations.
     *
     * @return array<string> Executed migration names
     */
    public function run(): array
    {
        $this->ensureRepositoryExists();

        $migrations = $this->getPendingMigrations();
        $executed = [];

        if (empty($migrations)) {
            return $executed;
        }

        $batch = $this->repository->getLastBatchNumber() + 1;

        foreach ($migrations as $migrationFile) {
            $migration = $this->resolve($migrationFile);
            $this->runMigration($migration, $batch);
            $executed[] = $migration->getName();
        }

        return $executed;
    }

    /**
     * Rollback the last batch of migrations.
     *
     * @return array<string> Rolled back migration names
     */
    public function rollback(): array
    {
        $this->ensureRepositoryExists();

        $batch = $this->repository->getLastBatchNumber();

        if ($batch === 0) {
            return [];
        }

        $migrations = $this->repository->getMigrationsByBatch($batch);
        $rolledBack = [];

        foreach ($migrations as $migrationName) {
            $migrationFile = $this->findMigrationFile($migrationName);
            if ($migrationFile === null) {
                throw new MigrationException("Migration file not found for: {$migrationName}");
            }

            $migration = $this->resolve($migrationFile);
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration->getName();
        }

        return $rolledBack;
    }

    /**
     * Reset all migrations.
     *
     * @return array<string> Rolled back migration names
     */
    public function reset(): array
    {
        $this->ensureRepositoryExists();

        $rolledBack = [];

        while (($batch = $this->repository->getLastBatchNumber()) > 0) {
            $migrations = $this->repository->getMigrationsByBatch($batch);

            foreach ($migrations as $migrationName) {
                $migrationFile = $this->findMigrationFile($migrationName);
                if ($migrationFile !== null) {
                    $migration = $this->resolve($migrationFile);
                    $this->rollbackMigration($migration);
                    $rolledBack[] = $migration->getName();
                }
            }
        }

        return $rolledBack;
    }

    /**
     * Get all migration files.
     *
     * @return array<string> Migration file paths
     */
    public function getMigrationFiles(): array
    {
        $files = [];

        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $phpFiles = glob($path . '/*.php');
            if ($phpFiles !== false) {
                $files = array_merge($files, $phpFiles);
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Get pending migrations.
     *
     * @return array<string> Migration file paths
     */
    private function getPendingMigrations(): array
    {
        $files = $this->getMigrationFiles();
        $executed = $this->repository->getMigrations();

        return array_filter($files, function ($file) use ($executed) {
            $className = $this->getClassName($file);
            return !in_array($className, $executed, true);
        });
    }

    /**
     * Run a migration.
     */
    private function runMigration(MigrationInterface $migration, int $batch): void
    {
        $migration->up();
        $this->repository->log($migration->getName(), $batch);
    }

    /**
     * Rollback a migration.
     */
    private function rollbackMigration(MigrationInterface $migration): void
    {
        $migration->down();
        $this->repository->delete($migration->getName());
    }

    /**
     * Resolve a migration instance from a file.
     */
    private function resolve(string $file): MigrationInterface
    {
        $class = $this->getClassName($file);

        require_once $file;

        if (!class_exists($class)) {
            throw new MigrationException("Migration class not found: {$class}");
        }

        $migration = new $class($this->schema);

        if (!$migration instanceof MigrationInterface) {
            throw new MigrationException("Migration must implement MigrationInterface: {$class}");
        }

        return $migration;
    }

    /**
     * Get the class name from a migration file.
     */
    private function getClassName(string $file): string
    {
        $filename = basename($file, '.php');

        // Remove timestamp prefix (e.g., 2024_01_01_000000_)
        $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);

        // Convert snake_case to PascalCase
        $className = str_replace('_', ' ', $className);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);

        return $className;
    }

    /**
     * Find migration file by class name.
     */
    private function findMigrationFile(string $className): ?string
    {
        $files = $this->getMigrationFiles();

        foreach ($files as $file) {
            if ($this->getClassName($file) === $className) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Ensure the migration repository exists.
     */
    private function ensureRepositoryExists(): void
    {
        if (!$this->repository->repositoryExists()) {
            $this->repository->createRepository();
        }
    }

    /**
     * Add a migration path.
     */
    public function addPath(string $path): void
    {
        $this->paths[] = $path;
    }

    /**
     * Get all migration paths.
     *
     * @return array<string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }
}
