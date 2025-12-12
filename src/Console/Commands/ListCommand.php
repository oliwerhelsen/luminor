<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;

/**
 * Command to list all available commands.
 *
 * Displays a formatted list of all registered commands
 * grouped by namespace with their descriptions.
 */
final class ListCommand extends AbstractCommand
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    private string $applicationName = 'Lumina DDD Framework';
    private string $applicationVersion = '1.0.0';

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('list')
            ->setDescription('List all available commands')
            ->addArgument('namespace', [
                'description' => 'Filter commands by namespace (e.g., "make")',
                'required' => false,
            ]);
    }

    /**
     * Set the available commands.
     *
     * @param array<string, CommandInterface> $commands
     */
    public function setCommands(array $commands): self
    {
        $this->commands = $commands;
        return $this;
    }

    /**
     * Set application info for display.
     */
    public function setApplicationInfo(string $name, string $version): self
    {
        $this->applicationName = $name;
        $this->applicationVersion = $version;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $namespace = $input->getArgument('namespace');

        // Display header
        $output->newLine();
        $output->writeln(sprintf('<info>%s</info> <comment>%s</comment>', $this->applicationName, $this->applicationVersion));
        $output->newLine();

        // Group commands by namespace
        $grouped = $this->groupCommands($namespace);

        if (empty($grouped)) {
            if ($namespace !== null) {
                $output->warning(sprintf('No commands found in namespace "%s"', $namespace));
            } else {
                $output->warning('No commands available');
            }
            return 0;
        }

        // Display usage
        $output->writeln('<comment>Usage:</comment>');
        $output->writeln('  command [options] [arguments]');
        $output->newLine();

        // Display available commands
        $output->writeln('<comment>Available commands:</comment>');

        // Find the longest command name for padding
        $maxLength = 0;
        foreach ($grouped as $commands) {
            foreach ($commands as $command) {
                $maxLength = max($maxLength, strlen($command->getName()));
            }
        }

        // Display commands grouped by namespace
        foreach ($grouped as $groupName => $commands) {
            if ($groupName !== '') {
                $output->writeln(sprintf(' <info>%s</info>', $groupName));
            }

            foreach ($commands as $command) {
                $name = $command->getName();
                $padding = str_repeat(' ', $maxLength - strlen($name) + 2);
                $output->writeln(sprintf(
                    '  <info>%s</info>%s%s',
                    $name,
                    $padding,
                    $command->getDescription()
                ));
            }
        }

        $output->newLine();

        return 0;
    }

    /**
     * Group commands by their namespace.
     *
     * @param string|null $filterNamespace Optional namespace to filter by
     * @return array<string, array<CommandInterface>>
     */
    private function groupCommands(?string $filterNamespace): array
    {
        $grouped = [];

        foreach ($this->commands as $command) {
            $name = $command->getName();

            // Skip the list command itself
            if ($name === 'list') {
                continue;
            }

            // Extract namespace (e.g., "make" from "make:entity")
            $namespace = '';
            if (str_contains($name, ':')) {
                $namespace = explode(':', $name)[0];
            }

            // Filter by namespace if specified
            if ($filterNamespace !== null && $namespace !== $filterNamespace) {
                continue;
            }

            if (!isset($grouped[$namespace])) {
                $grouped[$namespace] = [];
            }

            $grouped[$namespace][] = $command;
        }

        // Sort namespaces (empty namespace first, then alphabetically)
        uksort($grouped, function (string $a, string $b): int {
            if ($a === '') {
                return -1;
            }
            if ($b === '') {
                return 1;
            }
            return strcmp($a, $b);
        });

        // Sort commands within each namespace
        foreach ($grouped as &$commands) {
            usort($commands, fn(CommandInterface $a, CommandInterface $b) => strcmp($a->getName(), $b->getName()));
        }

        return $grouped;
    }
}
