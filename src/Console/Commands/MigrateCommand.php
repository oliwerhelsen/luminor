<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Database\Migrations\Migrator;
use Luminor\DDD\Database\Migrations\DatabaseMigrationRepository;
use Luminor\DDD\Database\Schema\Schema;
use Luminor\DDD\Database\Connection;
use Luminor\DDD\Container\Container;
use PDO;

/**
 * Migrate Command
 *
 * Run database migrations.
 */
final class MigrateCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('migrate')
            ->setDescription('Run database migrations')
            ->addOption('path', [
                'description' => 'The path to the migrations directory',
                'default' => 'database/migrations',
            ])
            ->addOption('database', [
                'description' => 'The database connection to use',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $output->info('Running migrations...');

        try {
            $migrator = $this->getMigrator($input);
            $executed = $migrator->run();

            if (empty($executed)) {
                $output->info('Nothing to migrate.');
                return 0;
            }

            $output->success('Migrations completed successfully!');
            $output->info('Executed:');
            foreach ($executed as $migration) {
                $output->writeln("  - {$migration}");
            }

            return 0;
        } catch (\Exception $e) {
            $output->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get the migrator instance.
     */
    private function getMigrator(Input $input): Migrator
    {
        // Get database configuration
        $connection = $this->getDatabaseConnection($input);
        $schema = new Schema($connection);

        // Create repository
        $repository = new DatabaseMigrationRepository($connection);

        // Create migrator
        $path = getcwd() . '/' . $input->getOption('path');
        $migrator = new Migrator($repository, $schema, Container::getInstance(), [$path]);

        return $migrator;
    }

    /**
     * Get database connection from environment or config.
     */
    private function getDatabaseConnection(Input $input): Connection
    {
        // Try to get from environment
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
