<?php

declare(strict_types=1);

namespace Luminor\DDD\Server;

use Luminor\DDD\Server\Adapters\FpmServer;
use Luminor\DDD\Server\Adapters\FrankenPhpServer;
use Luminor\DDD\Server\Adapters\SwooleServer;

/**
 * Factory for creating server instances.
 *
 * Automatically selects the best available server adapter or creates
 * a specific one based on user preference.
 */
final class ServerFactory
{
    /**
     * Create a server instance for the specified type.
     *
     * @throws \InvalidArgumentException If the server type is not supported
     */
    public static function create(ServerType $type): ServerInterface
    {
        return match ($type) {
            ServerType::FPM => new FpmServer(),
            ServerType::SWOOLE => new SwooleServer(),
            ServerType::FRANKENPHP => new FrankenPhpServer(),
        };
    }

    /**
     * Create a server instance from a string type name.
     *
     * @throws \InvalidArgumentException If the server type is not recognized
     */
    public static function createFromString(string $type): ServerInterface
    {
        $type = strtolower(trim($type));

        $serverType = ServerType::tryFrom($type);

        if ($serverType === null) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown server type "%s". Available types: %s',
                $type,
                implode(', ', array_map(fn (ServerType $t) => $t->value, ServerType::cases()))
            ));
        }

        return self::create($serverType);
    }

    /**
     * Get the best available server based on installed extensions.
     *
     * Priority order:
     * 1. FrankenPHP (if binary available)
     * 2. Swoole (if extension loaded)
     * 3. FPM/Built-in (always available, fallback)
     *
     * @param bool $preferHighPerformance If true, prioritize high-performance servers
     */
    public static function getBestAvailable(bool $preferHighPerformance = false): ServerInterface
    {
        if ($preferHighPerformance) {
            // Check for high-performance options first
            $swoole = new SwooleServer();
            if ($swoole->isAvailable()) {
                return $swoole;
            }

            $frankenphp = new FrankenPhpServer();
            if ($frankenphp->isAvailable()) {
                return $frankenphp;
            }
        }

        // Default: return FPM (always available)
        return new FpmServer();
    }

    /**
     * Get all available servers on this system.
     *
     * @return array<ServerInterface>
     */
    public static function getAvailableServers(): array
    {
        $servers = [];

        foreach (ServerType::cases() as $type) {
            $server = self::create($type);
            if ($server->isAvailable()) {
                $servers[] = $server;
            }
        }

        return $servers;
    }

    /**
     * Get information about all server types.
     *
     * @return array<string, array{name: string, available: bool, description: string, requirements: array<string, mixed>}>
     */
    public static function getServerInfo(): array
    {
        $info = [];

        foreach (ServerType::cases() as $type) {
            $server = self::create($type);
            $info[$type->value] = [
                'name' => $server->getName(),
                'available' => $server->isAvailable(),
                'description' => $type->description(),
                'requirements' => $server->getRequirements(),
            ];
        }

        return $info;
    }

    /**
     * Check if a specific server type is available.
     */
    public static function isAvailable(ServerType $type): bool
    {
        return self::create($type)->isAvailable();
    }
}
