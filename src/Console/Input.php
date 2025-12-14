<?php

declare(strict_types=1);

namespace Luminor\Console;

/**
 * Represents console command input.
 *
 * Holds parsed arguments and options from the command line.
 */
final class Input
{
    /**
     * @param array<string, string> $arguments Named arguments
     * @param array<string, string|bool> $options Named options
     */
    public function __construct(
        private readonly array $arguments = [],
        private readonly array $options = [],
    ) {
    }

    /**
     * Get an argument value.
     */
    public function getArgument(string $name, ?string $default = null): ?string
    {
        return $this->arguments[$name] ?? $default;
    }

    /**
     * Check if an argument exists.
     */
    public function hasArgument(string $name): bool
    {
        return isset($this->arguments[$name]);
    }

    /**
     * Get all arguments.
     *
     * @return array<string, string>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get an option value.
     */
    public function getOption(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Check if an option exists.
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Get all options.
     *
     * @return array<string, string|bool>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
