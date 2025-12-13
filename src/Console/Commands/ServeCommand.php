<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;

/**
 * Command to start the built-in PHP development server.
 *
 * Provides a convenient way to run the application locally
 * without requiring a full web server setup.
 */
final class ServeCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('serve')
            ->setDescription('Start the built-in PHP development server')
            ->addOption('host', [
                'shortcut' => 'H',
                'description' => 'The host address to serve on',
                'default' => '127.0.0.1',
            ])
            ->addOption('port', [
                'shortcut' => 'p',
                'description' => 'The port to serve on',
                'default' => '8000',
            ])
            ->addOption('docroot', [
                'shortcut' => 'd',
                'description' => 'The document root (relative to project root)',
                'default' => 'public',
            ])
            ->addOption('no-reload', [
                'description' => 'Disable automatic browser reload message',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $host = $input->getOption('host', '127.0.0.1');
        $port = $input->getOption('port', '8000');
        $docroot = $input->getOption('docroot', 'public');

        // Ensure host and port are strings
        $host = is_string($host) ? $host : '127.0.0.1';
        $port = is_string($port) ? $port : '8000';
        $docroot = is_string($docroot) ? $docroot : 'public';

        // Validate port
        $portNumber = (int) $port;
        if ($portNumber < 1 || $portNumber > 65535) {
            $output->error('Port must be between 1 and 65535');
            return 1;
        }

        // Determine the document root path
        $projectRoot = $this->findProjectRoot();
        $documentRoot = $projectRoot . DIRECTORY_SEPARATOR . $docroot;

        if (!is_dir($documentRoot)) {
            $output->error(sprintf('Document root "%s" does not exist', $documentRoot));
            $output->comment('You can specify a different document root with --docroot option');
            return 1;
        }

        // Check if port is already in use
        if ($this->isPortInUse($host, $portNumber)) {
            $output->error(sprintf('Port %d is already in use', $portNumber));
            $output->comment('Try using a different port with --port option');
            return 1;
        }

        $serverUrl = sprintf('http://%s:%s', $host, $port);

        $output->newLine();
        $output->info('Luminor Development Server');
        $output->writeln(str_repeat('─', 40));
        $output->newLine();
        $output->writeln(sprintf('  <comment>Server running on:</comment> <info>%s</info>', $serverUrl));
        $output->writeln(sprintf('  <comment>Document root:</comment>     %s', $documentRoot));
        $output->newLine();
        $output->comment('Press Ctrl+C to stop the server');
        $output->newLine();

        // Build the command
        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($documentRoot)
        );

        // Check for a router file
        $routerFile = $documentRoot . DIRECTORY_SEPARATOR . 'router.php';
        if (file_exists($routerFile)) {
            $command .= ' ' . escapeshellarg($routerFile);
            $output->writeln(sprintf('  <comment>Using router:</comment>      %s', $routerFile));
            $output->newLine();
        }

        // Execute the server
        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Find the project root directory.
     */
    private function findProjectRoot(): string
    {
        // Start from current directory and look for composer.json
        $dir = getcwd();

        if ($dir === false) {
            return '.';
        }

        while ($dir !== dirname($dir)) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . 'composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        // Fall back to current directory
        return getcwd() ?: '.';
    }

    /**
     * Check if a port is already in use.
     */
    private function isPortInUse(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($connection !== false) {
            fclose($connection);
            return true;
        }

        return false;
    }
}
