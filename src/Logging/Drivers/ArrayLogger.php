<?php

declare(strict_types=1);

namespace Luminor\DDD\Logging\Drivers;

use Luminor\DDD\Logging\AbstractLogger;
use Luminor\DDD\Logging\LogLevel;

/**
 * Logger that stores messages in memory.
 *
 * Useful for testing to assert that specific messages were logged.
 */
final class ArrayLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array<string, mixed>, channel: string, timestamp: string}> */
    private array $logs = [];

    /**
     * @inheritDoc
     */
    protected function writeLog(LogLevel $level, string $message, array $context): void
    {
        $this->logs[] = [
            'level' => $level->value,
            'message' => $message,
            'context' => $context,
            'channel' => $this->channel,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get all logged messages.
     *
     * @return array<int, array{level: string, message: string, context: array<string, mixed>, channel: string, timestamp: string}>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Get logs filtered by level.
     *
     * @param string $level The log level to filter by
     *
     * @return array<int, array{level: string, message: string, context: array<string, mixed>, channel: string, timestamp: string}>
     */
    public function getLogsByLevel(string $level): array
    {
        return array_filter($this->logs, fn ($log) => $log['level'] === $level);
    }

    /**
     * Get logs filtered by channel.
     *
     * @param string $channel The channel to filter by
     *
     * @return array<int, array{level: string, message: string, context: array<string, mixed>, channel: string, timestamp: string}>
     */
    public function getLogsByChannel(string $channel): array
    {
        return array_filter($this->logs, fn ($log) => $log['channel'] === $channel);
    }

    /**
     * Check if a message was logged.
     *
     * @param string $message The message to search for (partial match)
     * @param string|null $level Optional level to filter by
     */
    public function hasLogged(string $message, ?string $level = null): bool
    {
        foreach ($this->logs as $log) {
            if ($level !== null && $log['level'] !== $level) {
                continue;
            }

            if (str_contains($log['message'], $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear all logged messages.
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    /**
     * Get the number of logged messages.
     */
    public function count(): int
    {
        return count($this->logs);
    }
}
