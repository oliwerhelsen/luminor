<?php

declare(strict_types=1);

namespace Lumina\DDD\Logging\Drivers;

use Lumina\DDD\Logging\AbstractLogger;
use Lumina\DDD\Logging\LogLevel;

/**
 * Logger that discards all messages.
 *
 * Useful for testing or when logging should be disabled entirely.
 */
final class NullLogger extends AbstractLogger
{
    /**
     * @inheritDoc
     */
    protected function writeLog(LogLevel $level, string $message, array $context): void
    {
        // Intentionally empty - discard all log messages
    }
}
