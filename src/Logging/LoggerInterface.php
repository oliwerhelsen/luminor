<?php

declare(strict_types=1);

namespace Luminor\DDD\Logging;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Extended logger interface for Luminor applications.
 *
 * Extends PSR-3 LoggerInterface with additional convenience methods
 * and channel support.
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Get a logger instance for a specific channel.
     *
     * @param string $channel The channel name
     * @return LoggerInterface
     */
    public function channel(string $channel): LoggerInterface;

    /**
     * Get the current channel name.
     *
     * @return string
     */
    public function getChannel(): string;

    /**
     * Log a message with context and return $this for chaining.
     *
     * @param string $level The log level
     * @param string|\Stringable $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function write(string $level, string|\Stringable $message, array $context = []): void;
}
