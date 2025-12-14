<?php

declare(strict_types=1);

namespace Luminor\Logging\Drivers;

use Luminor\Logging\AbstractLogger;
use Luminor\Logging\LogLevel;

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
