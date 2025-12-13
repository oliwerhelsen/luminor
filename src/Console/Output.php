<?php

declare(strict_types=1);

namespace Luminor\DDD\Console;

/**
 * Handles console output with formatting support.
 *
 * Provides methods for writing formatted output to the console,
 * including colors and text styles.
 */
final class Output
{
    private bool $ansi = true;

    /**
     * Color and style definitions.
     */
    private const STYLES = [
        'info' => "\033[32m",      // Green
        'comment' => "\033[33m",   // Yellow
        'error' => "\033[31m",     // Red
        'warning' => "\033[33m",   // Yellow
        'success' => "\033[32m",   // Green
        'question' => "\033[36m",  // Cyan
        'bold' => "\033[1m",
        'reset' => "\033[0m",
    ];

    public function __construct()
    {
        // Detect if ANSI is supported
        $this->ansi = $this->detectAnsiSupport();
    }

    /**
     * Enable or disable ANSI output.
     */
    public function setAnsi(bool $ansi): self
    {
        $this->ansi = $ansi;
        return $this;
    }

    /**
     * Write a message to output.
     */
    public function write(string $message): void
    {
        echo $this->format($message);
    }

    /**
     * Write a message to output followed by a newline.
     */
    public function writeln(string $message = ''): void
    {
        echo $this->format($message) . PHP_EOL;
    }

    /**
     * Write a line separator.
     */
    public function line(string $message = ''): void
    {
        $this->writeln($message);
    }

    /**
     * Write an info message (green).
     */
    public function info(string $message): void
    {
        $this->writeln("<info>$message</info>");
    }

    /**
     * Write a comment message (yellow).
     */
    public function comment(string $message): void
    {
        $this->writeln("<comment>$message</comment>");
    }

    /**
     * Write a warning message (yellow).
     */
    public function warning(string $message): void
    {
        $this->writeln("<warning>Warning: $message</warning>");
    }

    /**
     * Write an error message (red).
     */
    public function error(string $message): void
    {
        $this->writeln("<error>Error: $message</error>");
    }

    /**
     * Write a success message (green).
     */
    public function success(string $message): void
    {
        $this->writeln("<success>✓ $message</success>");
    }

    /**
     * Write a question message (cyan).
     */
    public function question(string $message): void
    {
        $this->writeln("<question>$message</question>");
    }

    /**
     * Write a newline.
     */
    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            echo PHP_EOL;
        }
    }

    /**
     * Write a table to output.
     *
     * @param array<string> $headers
     * @param array<array<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        // Print separator
        $separator = '+';
        foreach ($widths as $width) {
            $separator .= str_repeat('-', $width + 2) . '+';
        }

        $this->writeln($separator);

        // Print headers
        $headerLine = '|';
        foreach ($headers as $i => $header) {
            $headerLine .= ' ' . str_pad($header, $widths[$i]) . ' |';
        }
        $this->writeln("<info>$headerLine</info>");
        $this->writeln($separator);

        // Print rows
        foreach ($rows as $row) {
            $rowLine = '|';
            foreach ($row as $i => $cell) {
                $rowLine .= ' ' . str_pad((string) $cell, $widths[$i]) . ' |';
            }
            $this->writeln($rowLine);
        }

        $this->writeln($separator);
    }

    /**
     * Format a message with styles.
     */
    private function format(string $message): string
    {
        if (!$this->ansi) {
            // Strip all tags
            return preg_replace('/<\/?[a-z]+>/', '', $message) ?? $message;
        }

        // Replace style tags with ANSI codes
        $replacements = [];
        foreach (self::STYLES as $style => $code) {
            $replacements["<$style>"] = $code;
            $replacements["</$style>"] = self::STYLES['reset'];
        }

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Detect if ANSI colors are supported.
     */
    private function detectAnsiSupport(): bool
    {
        // Check for NO_COLOR environment variable
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        // Check for TERM environment variable
        $term = getenv('TERM');
        if ($term === false || $term === 'dumb') {
            return false;
        }

        // Windows 10+ supports ANSI
        if (DIRECTORY_SEPARATOR === '\\') {
            return function_exists('sapi_windows_vt100_support')
                && sapi_windows_vt100_support(STDOUT);
        }

        // Unix systems generally support ANSI
        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    /**
     * Ask the user a question and return their input.
     *
     * @param string $question The question to ask
     * @param string|null $default The default value if no input is provided
     * @return string|null The user's input or default value
     */
    public function ask(string $question, ?string $default = null): ?string
    {
        $defaultDisplay = $default !== null ? " <comment>[$default]</comment>" : '';
        $this->write("<question>$question</question>$defaultDisplay: ");

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return $default;
        }

        $input = fgets($handle);
        fclose($handle);

        if ($input === false) {
            return $default;
        }

        $input = trim($input);

        return $input !== '' ? $input : $default;
    }

    /**
     * Ask the user a secret question (input hidden).
     *
     * @param string $question The question to ask
     * @return string|null The user's input
     */
    public function secret(string $question): ?string
    {
        $this->write("<question>$question</question>: ");

        // Disable echo on Unix systems
        if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
            shell_exec('stty -echo');
            $handle = fopen('php://stdin', 'r');
            $input = $handle ? trim(fgets($handle) ?: '') : null;
            if ($handle) {
                fclose($handle);
            }
            shell_exec('stty echo');
            $this->newLine();
            return $input !== '' ? $input : null;
        }

        // Fallback for Windows or when stty is not available
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return null;
        }

        $input = trim(fgets($handle) ?: '');
        fclose($handle);

        return $input !== '' ? $input : null;
    }

    /**
     * Ask the user for confirmation.
     *
     * @param string $question The question to ask
     * @param bool $default The default value if no input is provided
     * @return bool The user's confirmation
     */
    public function confirm(string $question, bool $default = false): bool
    {
        $defaultDisplay = $default ? 'Y/n' : 'y/N';
        $this->write("<question>$question</question> <comment>[$defaultDisplay]</comment>: ");

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return $default;
        }

        $input = fgets($handle);
        fclose($handle);

        if ($input === false || trim($input) === '') {
            return $default;
        }

        $input = strtolower(trim($input));

        return in_array($input, ['y', 'yes', '1', 'true'], true);
    }

    /**
     * Ask the user to choose from a list of options.
     *
     * @param string $question The question to ask
     * @param array<string, string> $options Associative array of key => label options
     * @param string|null $default The default option key
     * @return string The selected option key
     */
    public function choice(string $question, array $options, ?string $default = null): string
    {
        $this->writeln("<question>$question</question>");

        $keys = array_keys($options);
        $index = 1;
        $indexMap = [];

        foreach ($options as $key => $label) {
            $defaultMarker = ($key === $default) ? ' <comment>(default)</comment>' : '';
            $this->writeln("  <info>[$index]</info> $label$defaultMarker");
            $indexMap[$index] = $key;
            $index++;
        }

        $defaultDisplay = $default !== null ? " <comment>[$default]</comment>" : '';
        $this->write("Your choice$defaultDisplay: ");

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return $default ?? $keys[0];
        }

        $input = fgets($handle);
        fclose($handle);

        if ($input === false || trim($input) === '') {
            return $default ?? $keys[0];
        }

        $input = trim($input);

        // Check if input is a number (index)
        if (is_numeric($input)) {
            $inputIndex = (int) $input;
            if (isset($indexMap[$inputIndex])) {
                return $indexMap[$inputIndex];
            }
        }

        // Check if input matches an option key
        if (isset($options[$input])) {
            return $input;
        }

        // Invalid input, return default
        $this->warning("Invalid selection, using default.");
        return $default ?? $keys[0];
    }

    /**
     * Display a progress indicator.
     *
     * @param string $message The message to display
     */
    public function spinner(string $message): void
    {
        $this->write("<comment>⏳</comment> $message...");
    }

    /**
     * Complete a progress indicator.
     *
     * @param string $message The completion message
     */
    public function spinnerDone(string $message = 'Done'): void
    {
        $this->writeln(" <success>✓</success> $message");
    }
}
