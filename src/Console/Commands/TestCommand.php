<?php

declare(strict_types=1);

namespace Luminor\Console\Commands;

use Luminor\Console\Input;
use Luminor\Console\Output;

/**
 * Command to run PHPUnit tests.
 *
 * Provides a convenient way to run tests with common options.
 */
final class TestCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Run the application tests')
            ->addArgument('filter', [
                'description' => 'Filter which tests to run (class name, method name, or pattern)',
                'required' => false,
            ])
            ->addOption('coverage', [
                'shortcut' => 'c',
                'description' => 'Generate code coverage report',
            ])
            ->addOption('coverage-html', [
                'description' => 'Generate HTML code coverage report to the specified directory',
                'default' => null,
            ])
            ->addOption('testsuite', [
                'shortcut' => 's',
                'description' => 'Filter which testsuite to run',
                'default' => null,
            ])
            ->addOption('group', [
                'shortcut' => 'g',
                'description' => 'Only run tests from the specified group(s)',
                'default' => null,
            ])
            ->addOption('exclude-group', [
                'description' => 'Exclude tests from the specified group(s)',
                'default' => null,
            ])
            ->addOption('stop-on-failure', [
                'description' => 'Stop execution upon first error or failure',
            ])
            ->addOption('stop-on-error', [
                'description' => 'Stop execution upon first error',
            ])
            ->addOption('parallel', [
                'shortcut' => 'p',
                'description' => 'Run tests in parallel (requires ParaTest)',
            ])
            ->addOption('processes', [
                'description' => 'Number of parallel processes (default: auto)',
                'default' => null,
            ])
            ->addOption('verbose', [
                'shortcut' => 'v',
                'description' => 'Increase verbosity of messages',
            ])
            ->addOption('configuration', [
                'description' => 'Read configuration from XML file',
                'default' => null,
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $projectRoot = $this->findProjectRoot();
        $phpunitBinary = $this->findPhpUnitBinary($projectRoot);

        if ($phpunitBinary === null) {
            $output->error('PHPUnit not found. Please install it via Composer:');
            $output->writeln('  composer require --dev phpunit/phpunit');
            return 1;
        }

        // Build the PHPUnit command
        $command = [PHP_BINARY, $phpunitBinary];

        // Add configuration file if exists
        $configOption = $input->getOption('configuration');
        if ($configOption !== null && is_string($configOption)) {
            $command[] = '--configuration';
            $command[] = $configOption;
        } elseif (file_exists($projectRoot . '/phpunit.xml')) {
            $command[] = '--configuration';
            $command[] = $projectRoot . '/phpunit.xml';
        } elseif (file_exists($projectRoot . '/phpunit.xml.dist')) {
            $command[] = '--configuration';
            $command[] = $projectRoot . '/phpunit.xml.dist';
        }

        // Add filter argument
        $filter = $input->getArgument('filter');
        if ($filter !== null && is_string($filter) && $filter !== '') {
            $command[] = '--filter';
            $command[] = $filter;
        }

        // Add testsuite option
        $testsuite = $input->getOption('testsuite');
        if ($testsuite !== null && is_string($testsuite)) {
            $command[] = '--testsuite';
            $command[] = $testsuite;
        }

        // Add group option
        $group = $input->getOption('group');
        if ($group !== null && is_string($group)) {
            $command[] = '--group';
            $command[] = $group;
        }

        // Add exclude-group option
        $excludeGroup = $input->getOption('exclude-group');
        if ($excludeGroup !== null && is_string($excludeGroup)) {
            $command[] = '--exclude-group';
            $command[] = $excludeGroup;
        }

        // Add coverage options
        if ($input->hasOption('coverage') && $input->getOption('coverage')) {
            $command[] = '--coverage-text';
        }

        $coverageHtml = $input->getOption('coverage-html');
        if ($coverageHtml !== null && is_string($coverageHtml)) {
            $command[] = '--coverage-html';
            $command[] = $coverageHtml;
        }

        // Add stop-on options
        if ($input->hasOption('stop-on-failure') && $input->getOption('stop-on-failure')) {
            $command[] = '--stop-on-failure';
        }

        if ($input->hasOption('stop-on-error') && $input->getOption('stop-on-error')) {
            $command[] = '--stop-on-error';
        }

        // Add verbose option
        if ($input->hasOption('verbose') && $input->getOption('verbose')) {
            $command[] = '--verbose';
        }

        // Add colors
        $command[] = '--colors=always';

        // Display info
        $output->info('Running tests...');
        $output->writeln('');

        // Execute PHPUnit
        $process = proc_open(
            $command,
            [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ],
            $pipes,
            $projectRoot
        );

        if (!is_resource($process)) {
            $output->error('Failed to start PHPUnit process');
            return 1;
        }

        return proc_close($process);
    }

    /**
     * Find the project root directory.
     */
    private function findProjectRoot(): string
    {
        // Start from current directory
        $dir = getcwd();

        if ($dir === false) {
            return '.';
        }

        // Look for common project root indicators
        $indicators = ['composer.json', 'phpunit.xml', 'phpunit.xml.dist', '.git'];

        while ($dir !== '/') {
            foreach ($indicators as $indicator) {
                if (file_exists($dir . '/' . $indicator)) {
                    return $dir;
                }
            }
            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                break;
            }
            $dir = $parentDir;
        }

        return getcwd() ?: '.';
    }

    /**
     * Find the PHPUnit binary.
     */
    private function findPhpUnitBinary(string $projectRoot): ?string
    {
        $possiblePaths = [
            $projectRoot . '/vendor/bin/phpunit',
            $projectRoot . '/vendor/phpunit/phpunit/phpunit',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Check if PHPUnit is available globally
        $globalPhpUnit = shell_exec('which phpunit 2>/dev/null');
        if ($globalPhpUnit !== null && trim($globalPhpUnit) !== '') {
            return trim($globalPhpUnit);
        }

        return null;
    }

    /**
     * Set the command name.
     */
    protected function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the command description.
     */
    protected function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Add an argument to the command.
     *
     * @param string $name The argument name
     * @param array{description?: string, required?: bool, default?: string|null} $config Argument configuration
     */
    protected function addArgument(string $name, array $config = []): self
    {
        $this->arguments[$name] = $config;
        return $this;
    }

    /**
     * Add an option to the command.
     *
     * @param string $name The option name
     * @param array{shortcut?: string, description?: string, default?: string|bool|null} $config Option configuration
     */
    protected function addOption(string $name, array $config = []): self
    {
        $this->options[$name] = $config;
        return $this;
    }
}
