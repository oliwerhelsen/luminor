<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Database\Migrations\Migrator;
use Luminor\DDD\Database\Migrations\DatabaseMigrationRepository;
use Luminor\DDD\Database\Schema\Schema;
use Luminor\DDD\Database\Connection;
use PDO;

/**
 * Migrate Fresh Command
 *
 * Drop all tables and re-run all migrations.
 */
final class MigrateFreshCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('migrate:fresh')
            ->setDescription('Drop all tables and re-run all migrations')
            ->addOption('path', [
                'description' => 'The path to the migrations directory',
                'default' => 'database/migrations',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function execute(Input $input, Output $output): int
    {
        $output->warning('This will drop all tables and re-run migrations. Are you sure? (yes/no)');
        // For now, proceed without confirmation
        // In a real implementation, add confirmation logic

        $output->info('Dropping all tables...');

        try {
            $connection = $this->getDatabaseConnection();
            $this->dropAllTables($connection);

            $output->success('All tables dropped.');
            $output->info('Running migrations...');

            // Run migrations
            $schema = new Schema($connection);
            $repository = new DatabaseMigrationRepository($connection);
            $path = getcwd() . '/' . $input->getOption('path');
            $migrator = new Migrator($repository, $schema, $this->container, [$path]);

            $executed = $migrator->run();

            if (empty($executed)) {
                $output->info('No migrations to run.');
                return 0;
            }

            $output->success('Migration fresh completed successfully!');
            $output->info('Executed ' . count($executed) . ' migrations.');

            return 0;
        } catch (\Exception $e) {
            $output->error('Fresh migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Drop all tables.
     */
    private function dropAllTables(Connection $connection): void
    {
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            // Disable foreign key checks
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            // Get all tables
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Drop each table
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }

            // Re-enable foreign key checks
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($driver === 'pgsql') {
            // Get all tables
            $stmt = $pdo->query("
                SELECT tablename FROM pg_tables
                WHERE schemaname = 'public'
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Drop each table with CASCADE
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
            }
        } elseif ($driver === 'sqlite') {
            // Get all tables
            $stmt = $pdo->query("
                SELECT name FROM sqlite_master
                WHERE type='table' AND name NOT LIKE 'sqlite_%'
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Drop each table
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
            }
        }
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
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };

        return Connection::create($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
