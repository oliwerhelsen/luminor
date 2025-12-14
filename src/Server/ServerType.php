<?php

declare(strict_types=1);

namespace Luminor\Server;

/**
 * Enum representing available server types.
 */
enum ServerType: string
{
    case FPM = 'fpm';
    case SWOOLE = 'swoole';
    case FRANKENPHP = 'frankenphp';

    /**
     * Get the display name for this server type.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::FPM => 'PHP Built-in Server (FPM-compatible)',
            self::SWOOLE => 'Swoole',
            self::FRANKENPHP => 'FrankenPHP',
        };
    }

    /**
     * Get the description for this server type.
     */
    public function description(): string
    {
        return match ($this) {
            self::FPM => 'Standard PHP development server, no additional extensions required',
            self::SWOOLE => 'High-performance async server with coroutines (requires ext-swoole)',
            self::FRANKENPHP => 'Modern PHP application server built on Caddy (requires FrankenPHP binary)',
        };
    }

    /**
     * Check if this server type requires additional dependencies.
     */
    public function requiresExtension(): bool
    {
        return match ($this) {
            self::FPM => false,
            self::SWOOLE => true,
            self::FRANKENPHP => false, // Uses external binary
        };
    }

    /**
     * Get the required extension name (if any).
     */
    public function requiredExtension(): ?string
    {
        return match ($this) {
            self::FPM => null,
            self::SWOOLE => 'swoole',
            self::FRANKENPHP => null,
        };
    }
}
