<?php

declare(strict_types=1);

namespace Luminor\DDD\Server;

/**
 * Interface for HTTP server adapters.
 *
 * Inspired by Laravel Octane, this interface defines the contract
 * for different server implementations (PHP-FPM, Swoole, FrankenPHP).
 */
interface ServerInterface
{
    /**
     * Get the server name.
     */
    public function getName(): string;

    /**
     * Check if this server adapter is available.
     *
     * Returns true if all required extensions and dependencies are installed.
     */
    public function isAvailable(): bool;

    /**
     * Start the HTTP server.
     *
     * @param string $host The host to bind to
     * @param int $port The port to listen on
     * @param string $documentRoot The document root path
     * @param array<string, mixed> $options Additional server options
     */
    public function start(string $host, int $port, string $documentRoot, array $options = []): void;

    /**
     * Stop the HTTP server.
     */
    public function stop(): void;

    /**
     * Check if the server is currently running.
     */
    public function isRunning(): bool;

    /**
     * Get server-specific configuration requirements.
     *
     * @return array<string, mixed>
     */
    public function getRequirements(): array;
}
