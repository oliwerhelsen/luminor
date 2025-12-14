<?php

declare(strict_types=1);

namespace Luminor\DDD\Server\Adapters;

use Luminor\DDD\Server\ServerInterface;
use Luminor\DDD\Server\ServerType;

/**
 * FrankenPHP Server adapter.
 *
 * Modern PHP application server built on Caddy.
 * Supports both classic mode (FPM drop-in) and worker mode (persistent).
 *
 * @see https://frankenphp.dev/
 */
final class FrankenPhpServer implements ServerInterface
{
    private bool $running = false;

    /** @var resource|null */
    private $process = null;

    public function getName(): string
    {
        return ServerType::FRANKENPHP->displayName();
    }

    public function isAvailable(): bool
    {
        // Check if frankenphp binary is available
        $output = [];
        $returnCode = 0;

        exec('which frankenphp 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0) {
            return true;
        }

        // Also check common installation paths
        $commonPaths = [
            '/usr/local/bin/frankenphp',
            '/usr/bin/frankenphp',
            getenv('HOME') . '/.local/bin/frankenphp',
            './frankenphp',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return true;
            }
        }

        return false;
    }

    public function start(string $host, int $port, string $documentRoot, array $options = []): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                "FrankenPHP is not installed. Install it from: https://frankenphp.dev/docs/install/\n" .
                "Quick install: curl https://frankenphp.dev/install.sh | sh"
            );
        }

        $workerMode = $options['worker_mode'] ?? false;
        $workers = $options['workers'] ?? 4;
        $binary = $this->findBinary();
        $documentRoot = realpath($documentRoot) ?: $documentRoot;

        if ($workerMode) {
            $this->startWorkerMode($binary, $host, $port, $documentRoot, $workers, $options);
        } else {
            $this->startClassicMode($binary, $host, $port, $documentRoot, $options);
        }
    }

    /**
     * Start FrankenPHP in classic mode (FPM drop-in replacement).
     */
    private function startClassicMode(
        string $binary,
        string $host,
        int $port,
        string $documentRoot,
        array $options
    ): void {
        // Create a Caddyfile for FrankenPHP
        $caddyfile = $this->generateCaddyfile($host, $port, $documentRoot, false, $options);
        $caddyfilePath = sys_get_temp_dir() . '/luminor_frankenphp_Caddyfile';

        file_put_contents($caddyfilePath, $caddyfile);

        $command = sprintf(
            '%s run --config %s',
            escapeshellarg($binary),
            escapeshellarg($caddyfilePath)
        );

        $this->running = true;

        passthru($command, $exitCode);

        $this->running = false;

        // Clean up
        @unlink($caddyfilePath);

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf('FrankenPHP exited with code %d', $exitCode));
        }
    }

    /**
     * Start FrankenPHP in worker mode (persistent, high performance).
     */
    private function startWorkerMode(
        string $binary,
        string $host,
        int $port,
        string $documentRoot,
        int $workers,
        array $options
    ): void {
        $caddyfile = $this->generateCaddyfile($host, $port, $documentRoot, true, array_merge($options, [
            'workers' => $workers,
        ]));
        $caddyfilePath = sys_get_temp_dir() . '/luminor_frankenphp_Caddyfile';

        file_put_contents($caddyfilePath, $caddyfile);

        $command = sprintf(
            '%s run --config %s',
            escapeshellarg($binary),
            escapeshellarg($caddyfilePath)
        );

        $this->running = true;

        passthru($command, $exitCode);

        $this->running = false;

        // Clean up
        @unlink($caddyfilePath);

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf('FrankenPHP worker mode exited with code %d', $exitCode));
        }
    }

    /**
     * Generate a Caddyfile configuration.
     */
    private function generateCaddyfile(
        string $host,
        int $port,
        string $documentRoot,
        bool $workerMode,
        array $options
    ): string {
        $entryPoint = $options['entry_point'] ?? 'index.php';
        $workers = $options['workers'] ?? 4;

        $config = <<<CADDY
{
    frankenphp
    order php_server before file_server
}

:{$port} {
    root * {$documentRoot}

CADDY;

        if ($workerMode) {
            $config .= <<<CADDY

    php_server {
        worker {$documentRoot}/{$entryPoint} {$workers}
    }
CADDY;
        } else {
            $config .= <<<CADDY

    php_server
CADDY;
        }

        $config .= "\n}\n";

        return $config;
    }

    /**
     * Find the FrankenPHP binary path.
     */
    private function findBinary(): string
    {
        $output = [];
        exec('which frankenphp 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            return $output[0];
        }

        $commonPaths = [
            '/usr/local/bin/frankenphp',
            '/usr/bin/frankenphp',
            getenv('HOME') . '/.local/bin/frankenphp',
            './frankenphp',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('FrankenPHP binary not found');
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getRequirements(): array
    {
        return [
            'extensions' => [],
            'binaries' => ['frankenphp'],
            'packages' => [],
            'install_url' => 'https://frankenphp.dev/docs/install/',
            'install_command' => 'curl https://frankenphp.dev/install.sh | sh',
        ];
    }
}
