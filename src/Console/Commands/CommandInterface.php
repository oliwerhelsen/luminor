<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;

/**
 * Interface for console commands.
 *
 * All framework CLI commands must implement this interface.
 */
interface CommandInterface
{
    /**
     * Get the command name.
     *
     * The name is used to invoke the command from the CLI.
     * Use colons to group related commands (e.g., 'make:entity').
     */
    public function getName(): string;

    /**
     * Get the command description.
     *
     * A short description shown in the command list.
     */
    public function getDescription(): string;

    /**
     * Get command argument definitions.
     *
     * Returns an array of argument definitions:
     * [
     *     'name' => [
     *         'description' => 'Argument description',
     *         'required' => true,
     *         'default' => null,
     *     ],
     * ]
     *
     * @return array<string, array{description?: string, required?: bool, default?: string|null}>
     */
    public function getArguments(): array;

    /**
     * Get command option definitions.
     *
     * Returns an array of option definitions:
     * [
     *     'option-name' => [
     *         'shortcut' => 'o',
     *         'description' => 'Option description',
     *         'default' => null,
     *     ],
     * ]
     *
     * @return array<string, array{shortcut?: string, description?: string, default?: string|bool|null}>
     */
    public function getOptions(): array;

    /**
     * Execute the command.
     *
     * @param Input $input The command input
     * @param Output $output The console output
     *
     * @return int Exit code (0 for success, non-zero for failure)
     */
    public function execute(Input $input, Output $output): int;
}
