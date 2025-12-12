<?php

declare(strict_types=1);

namespace Lumina\DDD\Logging;

use Lumina\DDD\Logging\Drivers\ArrayLogger;
use Lumina\DDD\Logging\Drivers\FileLogger;
use Lumina\DDD\Logging\Drivers\NullLogger;
use Lumina\DDD\Logging\Drivers\StdoutLogger;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use RuntimeException;
use Stringable;

/**
 * Log manager that handles multiple channels and drivers.
 *
 * Provides a central point for managing log channels and
 * resolving the appropriate driver based on configuration.
 */
final class LogManager implements LoggerInterface
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, LoggerInterface> */
    private array $channels = [];

    private string $defaultChannel;

    /** @var array<string, class-string<LoggerInterface>> */
    private array $customDrivers = [];

    /**
     * @param array<string, mixed> $config Logging configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultChannel = $config['default'] ?? 'file';
    }

    /**
     * Get a logger for a specific channel.
     *
     * @param string $channel The channel name
     * @return LoggerInterface
     */
    public function channel(string $channel): LoggerInterface
    {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = $this->resolveChannel($channel);
        }

        return $this->channels[$channel];
    }

    /**
     * @inheritDoc
     */
    public function getChannel(): string
    {
        return $this->defaultChannel;
    }

    /**
     * Get the default logger.
     *
     * @return LoggerInterface
     */
    public function driver(?string $driver = null): LoggerInterface
    {
        return $this->channel($driver ?? $this->defaultChannel);
    }

    /**
     * Register a custom driver.
     *
     * @param string $name The driver name
     * @param class-string<LoggerInterface> $class The driver class
     */
    public function extend(string $name, string $class): self
    {
        $this->customDrivers[$name] = $class;
        return $this;
    }

    /**
     * Resolve a channel to a logger instance.
     */
    private function resolveChannel(string $channel): LoggerInterface
    {
        $channelConfig = $this->config['channels'][$channel] ?? [];
        $driver = $channelConfig['driver'] ?? $channel;

        // Add channel name to config
        $channelConfig['channel'] = $channel;

        return $this->createDriver($driver, $channelConfig);
    }

    /**
     * Create a driver instance.
     *
     * @param string $driver The driver name
     * @param array<string, mixed> $config The driver configuration
     * @return LoggerInterface
     * @throws RuntimeException If the driver is not supported
     */
    private function createDriver(string $driver, array $config): LoggerInterface
    {
        // Check custom drivers first
        if (isset($this->customDrivers[$driver])) {
            return new ($this->customDrivers[$driver])($config);
        }

        return match ($driver) {
            'file', 'daily' => new FileLogger($config),
            'stdout', 'stderr', 'stream' => new StdoutLogger($config),
            'null' => new NullLogger($config),
            'array', 'memory' => new ArrayLogger($config),
            default => throw new RuntimeException(sprintf('Log driver [%s] is not supported.', $driver)),
        };
    }

    /**
     * Create a stack of multiple channels.
     *
     * @param array<string> $channels The channels to stack
     * @return LoggerInterface
     */
    public function stack(array $channels): LoggerInterface
    {
        return new StackLogger(
            array_map(fn($channel) => $this->channel($channel), $channels)
        );
    }

    /**
     * @inheritDoc
     */
    public function write(string $level, string|Stringable $message, array $context = []): void
    {
        $this->driver()->write($level, $message, $context);
    }

    // PSR-3 LoggerInterface methods - delegate to default driver

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->driver()->emergency($message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->driver()->alert($message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->driver()->critical($message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->driver()->error($message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->driver()->warning($message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->driver()->notice($message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->driver()->info($message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->driver()->debug($message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->driver()->log($level, $message, $context);
    }
}
