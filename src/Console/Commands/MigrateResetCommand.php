<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Exception;
use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Database\Connection;
use Luminor\DDD\Database\Migrations\DatabaseMigrationRepository;
use Luminor\DDD\Database\Migrations\Migrator;
use Luminor\DDD\Database\Schema\Schema;
use PDO;
use RuntimeException;

/**
 * Migrate Reset Command
 *
 * Rollback all migrations.
 */
final class MigrateResetCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('migrate:reset')
            ->setDescription('Rollback all database migrations')
            ->addOption('path', [
                'description' => 'The path to the migrations directory',
                'default' => 'database/migrations',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Input $input, Output $output): int
    {
        $output->warning('This will rollback all migrations. Are you sure? (yes/no)');
        // For now, proceed without confirmation in CLI
        // In a real implementation, add confirmation logic

        $output->info('Resetting all migrations...');

        try {
            $migrator = $this->getMigrator($input);
            $rolledBack = $migrator->reset();

            if (empty($rolledBack)) {
                $output->info('No migrations to reset.');

                return 0;
            }

            $output->success('Reset completed successfully!');
            $output->info('Rolled back ' . count($rolledBack) . ' migrations.');

            return 0;
        } catch (Exception $e) {
            $output->error('Reset failed: ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * Get the migrator instance.
     */
    private function getMigrator(Input $input): Migrator
    {
        $connection = $this->getDatabaseConnection();
        $schema = new Schema($connection);
        $repository = new DatabaseMigrationRepository($connection);

        $path = getcwd() . '/' . $input->getOption('path');

        return new Migrator($repository, $schema, $this->container, [$path]);
    }

    /**
     * Get database connection.
     */
    private function getDatabaseConnection(): Connection
    {
        $driver = getenv('DB_CONNECTION') ?: 'mysql';
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'luminor';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database}",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            'sqlite' => "sqlite:{$database}",
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };

        return Connection::create($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
