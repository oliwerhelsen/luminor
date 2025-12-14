<?php

declare(strict_types=1);

namespace Luminor\Console\Commands;

use Luminor\Console\Input;
use Luminor\Console\Output;

/**
 * Base class for console commands.
 *
 * Provides common functionality for all commands including
 * argument/option handling and output helpers.
 */
abstract class AbstractCommand implements CommandInterface
{
    protected string $name = '';
    protected string $description = '';

    /** @var array<string, array{description?: string, required?: bool, default?: string|null}> */
    protected array $arguments = [];

    /** @var array<string, array{shortcut?: string, description?: string, default?: string|bool|null}> */
    protected array $options = [];

    /**
     * Configure the command.
     *
     * Override this method to set name, description, arguments, and options.
     */
    abstract protected function configure(): void;

    /**
     * Handle the command execution.
     *
     * @param Input $input The command input
     * @param Output $output The console output
     * @return int Exit code (0 for success, non-zero for failure)
     */
    abstract protected function handle(Input $input, Output $output): int;

    public function __construct()
    {
        $this->configure();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @inheritDoc
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return array_merge($this->options, [
            'help' => [
                'shortcut' => 'h',
                'description' => 'Display help for the command',
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Input $input, Output $output): int
    {
        // Validate required arguments
        foreach ($this->arguments as $name => $definition) {
            if (($definition['required'] ?? false) && !$input->hasArgument($name)) {
                $output->error(sprintf('Missing required argument: %s', $name));
                return 1;
            }
        }

        return $this->handle($input, $output);
    }

    /**
     * Set the command name.
     */
    protected function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the command description.
     */
    protected function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Add an argument definition.
     *
     * @param array{description?: string, required?: bool, default?: string|null} $definition
     */
    protected function addArgument(string $name, array $definition = []): self
    {
        $this->arguments[$name] = $definition;
        return $this;
    }

    /**
     * Add an option definition.
     *
     * @param array{shortcut?: string, description?: string, default?: string|bool|null} $definition
     */
    protected function addOption(string $name, array $definition = []): self
    {
        $this->options[$name] = $definition;
        return $this;
    }
}
