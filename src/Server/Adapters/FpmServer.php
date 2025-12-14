<?php

declare(strict_types=1);

namespace Luminor\Server\Adapters;

use Luminor\Server\ServerInterface;
use Luminor\Server\ServerType;

/**
 * PHP Built-in Development Server adapter.
 *
 * This is the default server that works without any additional extensions.
 * Uses PHP's built-in development server which is FPM-compatible.
 */
final class FpmServer implements ServerInterface
{
    private bool $running = false;

    /** @var resource|null */
    private $process = null;

    public function getName(): string
    {
        return ServerType::FPM->displayName();
    }

    public function isAvailable(): bool
    {
        // PHP built-in server is always available
        return true;
    }

    public function start(string $host, int $port, string $documentRoot, array $options = []): void
    {
        $routerFile = $options['router'] ?? null;

        $command = sprintf(
            'php -S %s:%d -t %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($documentRoot)
        );

        if ($routerFile !== null && file_exists($routerFile)) {
            $command .= ' ' . escapeshellarg($routerFile);
        }

        $this->running = true;

        // Execute the server (blocking)
        passthru($command, $exitCode);

        $this->running = false;

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf('PHP built-in server exited with code %d', $exitCode));
        }
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
            'binaries' => ['php'],
            'packages' => [],
        ];
    }
}
