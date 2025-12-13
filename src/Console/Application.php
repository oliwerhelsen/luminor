<?php

declare(strict_types=1);

namespace Luminor\DDD\Console;

use Luminor\DDD\Console\Commands\CommandInterface;
use Luminor\DDD\Container\ContainerInterface;
use Throwable;

/**
 * Console application for CLI commands.
 *
 * Manages command registration and execution for the framework's CLI tools.
 */
final class Application
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    private ?ContainerInterface $container = null;

    private string $name = 'Luminor DDD Framework';

    private string $version = '1.0.0';

    /**
     * @param ContainerInterface|null $container Optional DI container
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->registerDefaultCommands();
    }

    /**
     * Set the application name.
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the application version.
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get the application name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the application version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Register default framework commands.
     */
    private function registerDefaultCommands(): void
    {
        // Core commands
        $listCommand = new Commands\ListCommand();
        $listCommand->setApplicationInfo($this->name, $this->version);
        $this->register($listCommand);
        $this->register(new Commands\ServeCommand());
        $this->register(new Commands\NewCommand());

        // DDD Make commands
        $this->register(new Commands\MakeEntityCommand());
        $this->register(new Commands\MakeRepositoryCommand());
        $this->register(new Commands\MakeCommandCommand());
        $this->register(new Commands\MakeQueryCommand());
        $this->register(new Commands\MakeControllerCommand());
        $this->register(new Commands\MakeModuleCommand());

        // Infrastructure Make commands
        $this->register(new Commands\MakeJobCommand());
        $this->register(new Commands\MakeMailCommand());
        $this->register(new Commands\MakeMiddlewareCommand());
        $this->register(new Commands\MakeProviderCommand());
        $this->register(new Commands\MakeMigrationCommand());

        // Queue commands (require container injection)
        $this->register(new Commands\QueueWorkCommand());
        $this->register(new Commands\QueueRetryCommand());
        $this->register(new Commands\QueueFailedCommand());
        $this->register(new Commands\QueueFlushCommand());

        // Migration commands
        $this->register(new Commands\MigrateCommand());
        $this->register(new Commands\MigrateRollbackCommand());
        $this->register(new Commands\MigrateResetCommand());
        $this->register(new Commands\MigrateFreshCommand());
        $this->register(new Commands\MigrateStatusCommand());
    }

    /**
     * Set the container for commands that need it.
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;

        // Inject container into queue commands
        foreach ($this->commands as $command) {
            if (method_exists($command, 'setContainer')) {
                $command->setContainer($container);
            }
        }

        return $this;
    }

    /**
     * Register a command.
     */
    public function register(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;

        return $this;
    }

    /**
     * Get all registered commands.
     *
     * @return array<string, CommandInterface>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Check if a command exists.
     */
    public function hasCommand(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * Get a command by name.
     */
    public function getCommand(string $name): ?CommandInterface
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * Run the console application.
     *
     * @param array<string> $argv Command line arguments
     *
     * @return int Exit code (0 for success, non-zero for failure)
     */
    public function run(array $argv = []): int
    {
        // Remove the script name
        array_shift($argv);

        if (empty($argv)) {
            $this->showHelp();

            return 0;
        }

        $commandName = array_shift($argv);

        if ($commandName === '--help' || $commandName === '-h') {
            $this->showHelp();

            return 0;
        }

        if ($commandName === '--version' || $commandName === '-v') {
            $this->showVersion();

            return 0;
        }

        if (! $this->hasCommand($commandName)) {
            $this->error(sprintf('Command "%s" not found.', $commandName));
            $this->showAvailableCommands();

            return 1;
        }

        $command = $this->getCommand($commandName);

        if ($command === null) {
            return 1;
        }

        // If running list command, inject commands
        if ($command instanceof Commands\ListCommand) {
            $command->setCommands($this->commands);
            $command->setApplicationInfo($this->name, $this->version);
        }

        // Parse arguments
        $input = $this->parseArguments($argv, $command);

        // Check for help flag
        if ($input->hasOption('help')) {
            $this->showCommandHelp($command);

            return 0;
        }

        try {
            return $command->execute($input, new Output());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    /**
     * Parse command line arguments into an Input object.
     *
     * @param array<string> $argv
     */
    private function parseArguments(array $argv, CommandInterface $command): Input
    {
        $arguments = [];
        $options = [];
        $argumentDefinitions = $command->getArguments();
        $argIndex = 0;
        $argNames = array_keys($argumentDefinitions);

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                // Long option
                $option = substr($arg, 2);
                if (str_contains($option, '=')) {
                    [$key, $value] = explode('=', $option, 2);
                    $options[$key] = $value;
                } else {
                    $options[$option] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                // Short option
                $option = substr($arg, 1);
                $options[$option] = true;
            } else {
                // Positional argument
                if (isset($argNames[$argIndex])) {
                    $arguments[$argNames[$argIndex]] = $arg;
                    $argIndex++;
                }
            }
        }

        return new Input($arguments, $options);
    }

    /**
     * Show application help.
     */
    private function showHelp(): void
    {
        $this->showVersion();
        $this->line('');
        $this->line('Usage:');
        $this->line('  command [options] [arguments]');
        $this->line('');
        $this->showAvailableCommands();
    }

    /**
     * Show version information.
     */
    private function showVersion(): void
    {
        $this->line(sprintf('%s <info>%s</info>', $this->name, $this->version));
    }

    /**
     * Show available commands.
     */
    private function showAvailableCommands(): void
    {
        $this->line('Available commands:');

        // Group commands by prefix
        $groups = [];
        foreach ($this->commands as $name => $command) {
            $parts = explode(':', $name);
            $group = count($parts) > 1 ? $parts[0] : 'general';
            $groups[$group][$name] = $command;
        }

        ksort($groups);

        foreach ($groups as $group => $commands) {
            if ($group !== 'general') {
                $this->line(sprintf(' <comment>%s</comment>', $group));
            }
            foreach ($commands as $name => $command) {
                $this->line(sprintf('  <info>%-20s</info> %s', $name, $command->getDescription()));
            }
        }
    }

    /**
     * Show help for a specific command.
     */
    private function showCommandHelp(CommandInterface $command): void
    {
        $this->line(sprintf('<info>%s</info>', $command->getName()));
        $this->line('');
        $this->line($command->getDescription());
        $this->line('');
        $this->line('Usage:');
        $this->line(sprintf('  %s [options] [arguments]', $command->getName()));

        $arguments = $command->getArguments();
        if (! empty($arguments)) {
            $this->line('');
            $this->line('Arguments:');
            foreach ($arguments as $name => $definition) {
                $required = ($definition['required'] ?? false) ? '' : ' (optional)';
                $default = isset($definition['default']) ? sprintf(' [default: %s]', $definition['default']) : '';
                $this->line(sprintf(
                    '  <info>%-15s</info> %s%s%s',
                    $name,
                    $definition['description'] ?? '',
                    $required,
                    $default,
                ));
            }
        }

        $options = $command->getOptions();
        if (! empty($options)) {
            $this->line('');
            $this->line('Options:');
            foreach ($options as $name => $definition) {
                $shortcut = isset($definition['shortcut']) ? sprintf('-%s, ', $definition['shortcut']) : '    ';
                $default = isset($definition['default']) ? sprintf(' [default: %s]', $definition['default']) : '';
                $this->line(sprintf(
                    '  %s<info>--%-12s</info> %s%s',
                    $shortcut,
                    $name,
                    $definition['description'] ?? '',
                    $default,
                ));
            }
        }
    }

    /**
     * Output a line.
     */
    private function line(string $message): void
    {
        echo $this->formatOutput($message) . PHP_EOL;
    }

    /**
     * Output an error message.
     */
    private function error(string $message): void
    {
        echo "\033[31mError: " . $message . "\033[0m" . PHP_EOL;
    }

    /**
     * Format output with color codes.
     */
    private function formatOutput(string $message): string
    {
        $replacements = [
            '<info>' => "\033[32m",
            '</info>' => "\033[0m",
            '<comment>' => "\033[33m",
            '</comment>' => "\033[0m",
            '<error>' => "\033[31m",
            '</error>' => "\033[0m",
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}
