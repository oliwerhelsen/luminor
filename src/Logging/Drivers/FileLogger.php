<?php

declare(strict_types=1);

namespace Luminor\Logging\Drivers;

use Luminor\Logging\AbstractLogger;
use Luminor\Logging\LogLevel;

/**
 * File-based logger with daily rotation.
 *
 * Writes log entries to files with automatic daily rotation.
 * Creates the log directory if it doesn't exist.
 */
final class FileLogger extends AbstractLogger
{
    private string $path;
    private int $maxFiles;
    private ?string $dateFormat;

    /**
     * @param array<string, mixed> $config Configuration options:
     *                                      - path: Log file path (required)
     *                                      - level: Minimum log level (default: debug)
     *                                      - max_files: Number of files to keep (default: 7)
     *                                      - date_format: Date format for rotation (default: Y-m-d)
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->path = $config['path'] ?? $this->getDefaultPath();
        $this->maxFiles = $config['max_files'] ?? 7;
        $this->dateFormat = $config['date_format'] ?? 'Y-m-d';
    }

    /**
     * @inheritDoc
     */
    protected function writeLog(LogLevel $level, string $message, array $context): void
    {
        $path = $this->getRotatedPath();
        $directory = dirname($path);

        // Ensure directory exists
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $line = $this->formatLogLine($level, $message, $context) . PHP_EOL;

        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

        // Clean up old files
        $this->cleanOldFiles();
    }

    /**
     * Get the path with date rotation applied.
     */
    private function getRotatedPath(): string
    {
        $info = pathinfo($this->path);
        $directory = $info['dirname'] ?? '.';
        $filename = $info['filename'] ?? 'app';
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '.log';

        $date = date($this->dateFormat);

        return sprintf('%s/%s-%s%s', $directory, $filename, $date, $extension);
    }

    /**
     * Get the default log path.
     */
    private function getDefaultPath(): string
    {
        // Try to use storage_path if available
        if (function_exists('storage_path')) {
            return storage_path('logs/app.log');
        }

        return getcwd() . '/storage/logs/app.log';
    }

    /**
     * Clean up old log files beyond the retention limit.
     */
    private function cleanOldFiles(): void
    {
        if ($this->maxFiles <= 0) {
            return;
        }

        $info = pathinfo($this->path);
        $directory = $info['dirname'] ?? '.';
        $filename = $info['filename'] ?? 'app';
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '.log';

        $pattern = sprintf('%s/%s-*%s', $directory, $filename, $extension);
        $files = glob($pattern);

        if ($files === false || count($files) <= $this->maxFiles) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));

        // Remove oldest files
        $filesToRemove = array_slice($files, 0, count($files) - $this->maxFiles);
        foreach ($filesToRemove as $file) {
            @unlink($file);
        }
    }

    /**
     * Get the current log file path.
     */
    public function getPath(): string
    {
        return $this->getRotatedPath();
    }
}
