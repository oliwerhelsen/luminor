<?php

declare(strict_types=1);

namespace Luminor\DDD\Logging\Drivers;

use Luminor\DDD\Logging\AbstractLogger;
use Luminor\DDD\Logging\LogLevel;

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
