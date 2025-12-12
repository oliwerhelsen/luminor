<?php

declare(strict_types=1);

namespace Lumina\DDD\Logging;

use Stringable;

/**
 * Logger that writes to multiple channels simultaneously.
 *
 * Useful for sending logs to multiple destinations at once,
 * such as both file and stdout.
 */
final class StackLogger implements LoggerInterface
{
    /** @var array<LoggerInterface> */
    private array $loggers;

    private string $channel = 'stack';

    /**
     * @param array<LoggerInterface> $loggers The loggers to stack
     */
    public function __construct(array $loggers)
    {
        $this->loggers = $loggers;
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

    public function emergency(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->emergency($message, $context);
        }
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->alert($message, $context);
        }
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->critical($message, $context);
        }
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->error($message, $context);
        }
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->warning($message, $context);
        }
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->notice($message, $context);
        }
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->info($message, $context);
        }
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->debug($message, $context);
        }
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }
}
