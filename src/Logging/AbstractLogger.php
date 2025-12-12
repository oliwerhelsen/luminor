<?php

declare(strict_types=1);

namespace Lumina\DDD\Logging;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Abstract base class for log drivers.
 *
 * Provides common functionality for all log drivers including
 * level filtering and message formatting.
 */
abstract class AbstractLogger extends AbstractLogger implements LoggerInterface
{
    protected string $channel = 'app';
    protected LogLevel $minimumLevel = LogLevel::DEBUG;

    /** @var array<string, mixed> */
    protected array $config = [];

    /**
     * @param array<string, mixed> $config Driver configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (isset($config['level'])) {
            $this->minimumLevel = LogLevel::fromString($config['level']);
        }

        if (isset($config['channel'])) {
            $this->channel = $config['channel'];
        }
    }

    /**
     * @inheritDoc
     */
    public function channel(string $channel): LoggerInterface
    {
        $clone = clone $this;
        $clone->channel = $channel;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @inheritDoc
     */
    public function write(string $level, string|Stringable $message, array $context = []): void
    {
        $this->log($level, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $logLevel = LogLevel::fromString((string) $level);

        // Check if this level should be logged
        if (!$logLevel->meetsMinimum($this->minimumLevel)) {
            return;
        }

        $this->writeLog($logLevel, $this->interpolate($message, $context), $context);
    }

    /**
     * Write the log entry to the destination.
     *
     * @param LogLevel $level The log level
     * @param string $message The formatted message
     * @param array<string, mixed> $context Additional context
     */
    abstract protected function writeLog(LogLevel $level, string $message, array $context): void;

    /**
     * Interpolate context values into the message.
     *
     * @param string|Stringable $message The message with placeholders
     * @param array<string, mixed> $context The context values
     * @return string The interpolated message
     */
    protected function interpolate(string|Stringable $message, array $context): string
    {
        $message = (string) $message;

        if (empty($context)) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            if ($key === 'exception') {
                continue;
            }

            if (is_string($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $value;
            } elseif (is_scalar($value)) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Format a log entry as a string.
     *
     * @param LogLevel $level The log level
     * @param string $message The message
     * @param array<string, mixed> $context Additional context
     * @return string The formatted log line
     */
    protected function formatLogLine(LogLevel $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = strtoupper($level->value);

        $line = sprintf(
            '[%s] %s.%s: %s',
            $timestamp,
            $this->channel,
            $levelName,
            $message
        );

        // Add context (excluding exception which is handled separately)
        $contextData = array_filter($context, fn($key) => $key !== 'exception', ARRAY_FILTER_USE_KEY);
        if (!empty($contextData)) {
            $line .= ' ' . json_encode($contextData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Add exception trace if present
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            $line .= sprintf(
                "\n[Exception] %s: %s in %s:%d\n%s",
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
        }

        return $line;
    }
}
