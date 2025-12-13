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
 * Migrate Status Command
 *
 * Show migration status.
 */
final class MigrateStatusCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('migrate:status')
            ->setDescription('Show the status of each migration')
            ->addOption('path', [
                'description' => 'The path to the migrations directory',
                'default' => 'database/migrations',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        try {
            $migrator = $this->getMigrator($input);
            $connection = $this->getDatabaseConnection();
            $repository = new DatabaseMigrationRepository($connection);

            if (!$repository->repositoryExists()) {
                $output->warning('Migration table does not exist. Run migrations first.');
                return 0;
            }

            $executed = $repository->getMigrations();
            $allMigrations = $migrator->getMigrationFiles();

            if (empty($allMigrations)) {
                $output->info('No migrations found.');
                return 0;
            }

            $output->info('Migration Status:');
            $output->writeln('');

            foreach ($allMigrations as $file) {
                $name = basename($file, '.php');
                $className = $this->getClassName($file);
                $status = in_array($className, $executed, true) ? 'Ran' : 'Pending';
                $statusColor = $status === 'Ran' ? 'green' : 'yellow';

                $output->writeln("  [{$statusColor}]{$status}[/{$statusColor}] {$name}");
            }

            $output->writeln('');
            $runCount = count(array_filter($allMigrations, function ($file) use ($executed) {
                return in_array($this->getClassName($file), $executed, true);
            }));
            $pendingCount = count($allMigrations) - $runCount;

            $output->info("Total: " . count($allMigrations) . " | Ran: {$runCount} | Pending: {$pendingCount}");

            return 0;
        } catch (\Exception $e) {
            $output->error('Failed to get status: ' . $e->getMessage());
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
        return new Migrator($repository, $schema, Container::getInstance(), [$path]);
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

    /**
     * Get class name from migration file.
     */
    private function getClassName(string $file): string
    {
        $filename = basename($file, '.php');
        $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
        $className = str_replace('_', ' ', $className);
        $className = ucwords($className);
        return str_replace(' ', '', $className);
    }
}
