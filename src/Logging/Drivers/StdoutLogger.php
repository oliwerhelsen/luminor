<?php

declare(strict_types=1);

namespace Luminor\DDD\Logging\Drivers;

use Luminor\DDD\Logging\AbstractLogger;
use Luminor\DDD\Logging\LogLevel;

/**
 * Logger that writes to stdout/stderr.
 *
 * Writes info and below to stdout, warnings and above to stderr.
 * Ideal for containerized environments and cloud deployments.
 */
final class StdoutLogger extends AbstractLogger
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param array<string, mixed> $config Configuration options:
     *                                      - level: Minimum log level (default: debug)
     *                                      - stderr_level: Minimum level for stderr (default: warning)
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->stdout = STDOUT;
        $this->stderr = STDERR;
    }

    /**
     * @inheritDoc
     */
    protected function writeLog(LogLevel $level, string $message, array $context): void
    {
        $line = $this->formatLogLine($level, $message, $context) . PHP_EOL;

        // Write warnings and above to stderr, everything else to stdout
        $stream = $level->meetsMinimum(LogLevel::WARNING) ? $this->stderr : $this->stdout;

        fwrite($stream, $line);
    }
}
