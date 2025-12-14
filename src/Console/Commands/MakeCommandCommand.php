<?php

declare(strict_types=1);

namespace Luminor\Console\Commands;

use Luminor\Console\Input;
use Luminor\Console\Output;

/**
 * Command to generate a new CQRS Command and Handler.
 *
 * Creates a command class for the CQRS pattern along with
 * its corresponding handler.
 */
final class MakeCommandCommand extends AbstractMakeCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:command')
            ->setDescription('Create a new CQRS command and handler')
            ->addArgument('name', [
                'description' => 'The name of the command (e.g., CreateUser, UpdateOrder)',
                'required' => true,
            ])
            ->addOption('no-handler', [
                'shortcut' => 'n',
                'description' => 'Do not create a handler class',
            ])
            ->addOption('path', [
                'shortcut' => 'p',
                'description' => 'Custom output path (relative to project root)',
            ])
            ->addOption('force', [
                'shortcut' => 'f',
                'description' => 'Overwrite existing files',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function getStubName(): string
    {
        return 'command.stub';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultDirectory(): string
    {
        return 'src/Application/Commands';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultNamespace(): string
    {
        return 'Application\\Commands';
    }

    /**
     * @inheritDoc
     */
    protected function getFileSuffix(): string
    {
        return 'Command.php';
    }

    /**
     * @inheritDoc
     */
    protected function buildReplacements(Input $input): array
    {
        $name = $input->getArgument('name') ?? '';

        // Remove 'Command' suffix if provided
        if (str_ends_with($name, 'Command')) {
            $name = substr($name, 0, -7);
        }

        $commandName = $this->studly($name);

        return [
            '{{ commandName }}' => $commandName,
            '{{commandName}}' => $commandName,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        // Create the command
        $result = parent::handle($input, $output);

        if ($result !== 0) {
            return $result;
        }

        // Create handler if not disabled
        if (!$input->hasOption('no-handler')) {
            $this->createHandler($input, $output);
        }

        return 0;
    }

    /**
     * Create the command handler.
     */
    private function createHandler(Input $input, Output $output): void
    {
        $name = $input->getArgument('name') ?? '';

        // Remove 'Command' suffix if provided
        if (str_ends_with($name, 'Command')) {
            $name = substr($name, 0, -7);
        }

        $commandName = $this->studly($name);
        $handlerName = $commandName . 'CommandHandler';

        $basePath = $this->getBasePath();
        $directory = $basePath . DIRECTORY_SEPARATOR . 'src/Application/Handlers';
        $filePath = $directory . DIRECTORY_SEPARATOR . $handlerName . '.php';

        if (file_exists($filePath) && !$input->hasOption('force')) {
            $output->warning(sprintf('File already exists: %s', $filePath));
            return;
        }

        // Load handler stub
        $stubContent = $this->loadHandlerStub();
        if ($stubContent === null) {
            $output->warning('Handler stub file not found: command-handler.stub');
            return;
        }

        $namespace = 'App\\Application\\Handlers';
        $commandNamespace = 'App\\Application\\Commands';

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $handlerName,
            '{{ commandName }}' => $commandName,
            '{{ commandNamespace }}' => $commandNamespace,
            '{{ commandVariable }}' => $this->camel($commandName) . 'Command',
            '{{namespace}}' => $namespace,
            '{{class}}' => $handlerName,
            '{{commandName}}' => $commandName,
            '{{commandNamespace}}' => $commandNamespace,
            '{{commandVariable}}' => $this->camel($commandName) . 'Command',
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stubContent
        );

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($filePath, $content);
        $output->success(sprintf('Created: %s', $filePath));
    }

    /**
     * Load the handler stub.
     */
    private function loadHandlerStub(): ?string
    {
        $stubPaths = [
            $this->getBasePath() . '/stubs/command-handler.stub',
            dirname(__DIR__, 3) . '/stubs/command-handler.stub',
            __DIR__ . '/../../../stubs/command-handler.stub',
        ];

        foreach ($stubPaths as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                return $content !== false ? $content : null;
            }
        }

        return null;
    }
}
