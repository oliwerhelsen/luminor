<?php

declare(strict_types=1);

namespace Luminor\Logging;

/**
 * Log level enumeration following PSR-3 log levels.
 */
enum LogLevel: string
{
    case EMERGENCY = 'emergency';
    case ALERT = 'alert';
    case CRITICAL = 'critical';
    case ERROR = 'error';
    case WARNING = 'warning';
    case NOTICE = 'notice';
    case INFO = 'info';
    case DEBUG = 'debug';

    /**
     * Get the numeric priority of the log level.
     * Lower number = higher priority.
     */
    public function priority(): int
    {
        return match ($this) {
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7,
        };
    }

    /**
     * Check if this level meets or exceeds the minimum level.
     */
    public function meetsMinimum(LogLevel $minimum): bool
    {
        return $this->priority() <= $minimum->priority();
    }

    /**
     * Create from string value.
     */
    public static function fromString(string $level): self
    {
        return self::from(strtolower($level));
    }
}
