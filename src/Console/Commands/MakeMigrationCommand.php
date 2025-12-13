<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;

/**
 * Make Migration Command
 *
 * Generates a new database migration file.
 */
final class MakeMigrationCommand extends AbstractMakeCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:migration')
            ->setDescription('Create a new migration file')
            ->addArgument('name', [
                'description' => 'The name of the migration (e.g., create_users_table)',
                'required' => true,
            ])
            ->addOption('table', [
                'description' => 'The table name',
                'shortcut' => 't',
            ])
            ->addOption('create', [
                'description' => 'The table to create',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function execute(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');
        $table = $input->getOption('table') ?? $input->getOption('create');

        // Generate timestamp prefix
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";

        // Determine migration path
        $migrationsPath = getcwd() . '/database/migrations';
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0755, true);
        }

        $filepath = $migrationsPath . '/' . $filename;

        // Check if file exists
        if (file_exists($filepath)) {
            $output->error("Migration already exists: {$filename}");
            return 1;
        }

        // Extract table name from migration name if not provided
        if (!$table) {
            if (preg_match('/create_(\w+)_table/', $name, $matches)) {
                $table = $matches[1];
            } elseif (preg_match('/(?:add|update|modify)_.*_to_(\w+)_table/', $name, $matches)) {
                $table = $matches[1];
            } else {
                $table = 'example';
            }
        }

        // Convert snake_case to PascalCase
        $className = str_replace('_', ' ', $name);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);

        // Generate migration content
        $stub = $this->getStub('migration');
        $content = str_replace(
            ['{{className}}', '{{table}}', '{{description}}'],
            [$className, $table, ucfirst(str_replace('_', ' ', $name))],
            $stub
        );

        // Write file
        file_put_contents($filepath, $content);

        $output->success("Migration created: {$filename}");
        $output->info("Location: {$filepath}");

        return 0;
    }
}
