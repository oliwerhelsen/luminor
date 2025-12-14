<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Server\ServerFactory;
use Luminor\DDD\Server\ServerType;

/**
 * Command to start the HTTP development server.
 *
 * Supports multiple server backends:
 * - fpm: PHP built-in development server (default, no extra requirements)
 * - swoole: High-performance async server (requires ext-swoole)
 * - frankenphp: Modern PHP application server (requires FrankenPHP binary)
 */
final class ServeCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('serve')
            ->setDescription('Start the HTTP development server')
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
            ->addOption('server', [
                'shortcut' => 's',
                'description' => 'Server type: fpm (default), swoole, or frankenphp',
                'default' => 'fpm',
            ])
            ->addOption('workers', [
                'shortcut' => 'w',
                'description' => 'Number of worker processes (swoole/frankenphp only)',
                'default' => '4',
            ])
            ->addOption('worker-mode', [
                'description' => 'Enable worker mode for persistent processes (frankenphp only)',
            ])
            ->addOption('list-servers', [
                'shortcut' => 'l',
                'description' => 'List all available server types',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        // Handle --list-servers option
        if ($input->hasOption('list-servers') && $input->getOption('list-servers')) {
            return $this->listServers($output);
        }

        $host = $input->getOption('host', '127.0.0.1');
        $port = $input->getOption('port', '8000');
        $docroot = $input->getOption('docroot', 'public');
        $serverType = $input->getOption('server', 'fpm');
        $workers = $input->getOption('workers', '4');
        $workerMode = $input->hasOption('worker-mode') && $input->getOption('worker-mode');

        // Ensure values are strings
        $host = is_string($host) ? $host : '127.0.0.1';
        $port = is_string($port) ? $port : '8000';
        $docroot = is_string($docroot) ? $docroot : 'public';
        $serverType = is_string($serverType) ? $serverType : 'fpm';
        $workers = is_string($workers) ? (int) $workers : 4;

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

        // Create the server
        try {
            $server = ServerFactory::createFromString($serverType);
        } catch (\InvalidArgumentException $e) {
            $output->error($e->getMessage());
            $output->comment('Use --list-servers to see available options');
            return 1;
        }

        // Check if the server is available
        if (!$server->isAvailable()) {
            $output->error(sprintf('Server "%s" is not available on this system', $server->getName()));

            $requirements = $server->getRequirements();
            if (!empty($requirements['install_command'])) {
                $output->comment(sprintf('Install with: %s', $requirements['install_command']));
            }
            if (!empty($requirements['install_url'])) {
                $output->comment(sprintf('More info: %s', $requirements['install_url']));
            }

            $output->newLine();
            $output->comment('Available servers on this system:');
            foreach (ServerFactory::getAvailableServers() as $available) {
                $output->writeln(sprintf('  - %s', $available->getName()));
            }

            return 1;
        }

        $serverUrl = sprintf('http://%s:%s', $host, $port);

        $output->newLine();
        $output->info('Luminor Development Server');
        $output->writeln(str_repeat('─', 50));
        $output->newLine();
        $output->writeln(sprintf('  <comment>Server:</comment>          <info>%s</info>', $server->getName()));
        $output->writeln(sprintf('  <comment>Running on:</comment>      <info>%s</info>', $serverUrl));
        $output->writeln(sprintf('  <comment>Document root:</comment>   %s', $documentRoot));

        if ($serverType !== 'fpm') {
            $output->writeln(sprintf('  <comment>Workers:</comment>         %d', $workers));
        }

        if ($workerMode && $serverType === 'frankenphp') {
            $output->writeln('  <comment>Mode:</comment>            Worker (persistent)');
        }

        $output->newLine();

        // Check for a router file
        $routerFile = $documentRoot . DIRECTORY_SEPARATOR . 'router.php';
        if (file_exists($routerFile)) {
            $output->writeln(sprintf('  <comment>Using router:</comment>   %s', $routerFile));
            $output->newLine();
        }

        $output->comment('Press Ctrl+C to stop the server');
        $output->newLine();

        // Start the server
        try {
            $server->start($host, $portNumber, $documentRoot, [
                'router' => $routerFile,
                'workers' => $workers,
                'worker_mode' => $workerMode,
                'entry_point' => 'index.php',
            ]);
        } catch (\RuntimeException $e) {
            $output->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * List all available server types.
     */
    private function listServers(Output $output): int
    {
        $output->newLine();
        $output->info('Available Server Types');
        $output->writeln(str_repeat('─', 50));
        $output->newLine();

        foreach (ServerFactory::getServerInfo() as $type => $info) {
            $status = $info['available'] ? '<info>✓ Available</info>' : '<comment>✗ Not installed</comment>';

            $output->writeln(sprintf('  <info>%s</info> (%s)', $info['name'], $type));
            $output->writeln(sprintf('    Status: %s', $status));
            $output->writeln(sprintf('    %s', $info['description']));

            if (!$info['available'] && !empty($info['requirements']['install_command'])) {
                $output->writeln(sprintf('    Install: <comment>%s</comment>', $info['requirements']['install_command']));
            }

            $output->newLine();
        }

        $output->writeln('Usage: <comment>luminor serve --server=TYPE</comment>');
        $output->newLine();

        return 0;
    }

    /**
     * Find the project root directory.
     */
    private function findProjectRoot(): string
    {
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
