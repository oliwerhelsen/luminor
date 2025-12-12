<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;

/**
 * Command to generate a new Repository interface and implementation.
 *
 * Creates both the repository interface (domain layer) and
 * optionally an implementation class (infrastructure layer).
 */
final class MakeRepositoryCommand extends AbstractMakeCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:repository')
            ->setDescription('Create a new repository interface and implementation')
            ->addArgument('name', [
                'description' => 'The name of the entity (e.g., User creates UserRepositoryInterface)',
                'required' => true,
            ])
            ->addOption('implementation', [
                'shortcut' => 'i',
                'description' => 'Also create an implementation class',
            ])
            ->addOption('in-memory', [
                'shortcut' => 'm',
                'description' => 'Create an in-memory implementation for testing',
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
        return 'repository-interface.stub';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultDirectory(): string
    {
        return 'src/Domain/Repository';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultNamespace(): string
    {
        return 'Domain\\Repository';
    }

    /**
     * @inheritDoc
     */
    protected function getFileSuffix(): string
    {
        return 'RepositoryInterface.php';
    }

    /**
     * @inheritDoc
     */
    protected function buildReplacements(Input $input): array
    {
        $name = $input->getArgument('name') ?? '';
        $entityClass = $this->studly($name);

        return [
            '{{ entity }}' => $entityClass,
            '{{ entityVariable }}' => $this->camel($entityClass),
            '{{ entityPlural }}' => $this->plural($entityClass),
            '{{entity}}' => $entityClass,
            '{{entityVariable}}' => $this->camel($entityClass),
            '{{entityPlural}}' => $this->plural($entityClass),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        // Create the interface first
        $result = parent::handle($input, $output);

        if ($result !== 0) {
            return $result;
        }

        // Create implementation if requested
        if ($input->hasOption('implementation') || $input->hasOption('in-memory')) {
            $name = $input->getArgument('name') ?? '';
            $entityClass = $this->studly($name);

            if ($input->hasOption('in-memory')) {
                $this->createImplementation(
                    $input,
                    $output,
                    $entityClass,
                    'InMemory' . $entityClass . 'Repository',
                    'repository.stub',
                    'src/Infrastructure/Persistence/InMemory'
                );
            }

            if ($input->hasOption('implementation')) {
                $this->createImplementation(
                    $input,
                    $output,
                    $entityClass,
                    $entityClass . 'Repository',
                    'repository.stub',
                    'src/Infrastructure/Persistence'
                );
            }
        }

        return 0;
    }

    /**
     * Create a repository implementation.
     */
    private function createImplementation(
        Input $input,
        Output $output,
        string $entityClass,
        string $implementationName,
        string $stubName,
        string $directory
    ): void {
        $basePath = $this->getBasePath();
        $fullDirectory = $basePath . DIRECTORY_SEPARATOR . $directory;
        $filePath = $fullDirectory . DIRECTORY_SEPARATOR . $implementationName . '.php';

        if (file_exists($filePath) && !$input->hasOption('force')) {
            $output->warning(sprintf('File already exists: %s', $filePath));
            return;
        }

        // Load repository implementation stub
        $stubContent = $this->loadStubByName($stubName);
        if ($stubContent === null) {
            $output->warning(sprintf('Stub file not found: %s', $stubName));
            return;
        }

        // Determine namespace
        $namespace = str_replace('/', '\\', $directory);
        $namespace = 'App\\' . str_replace('src\\', '', $namespace);
        $namespace = str_replace('src/', '', $namespace);
        $namespace = rtrim($namespace, '\\');

        // Build replacements
        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $implementationName,
            '{{ entity }}' => $entityClass,
            '{{ entityVariable }}' => $this->camel($entityClass),
            '{{ entityPlural }}' => $this->plural($entityClass),
            '{{ interfaceNamespace }}' => 'App\\Domain\\Repository',
            '{{namespace}}' => $namespace,
            '{{class}}' => $implementationName,
            '{{entity}}' => $entityClass,
            '{{entityVariable}}' => $this->camel($entityClass),
            '{{entityPlural}}' => $this->plural($entityClass),
            '{{interfaceNamespace}}' => 'App\\Domain\\Repository',
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stubContent
        );

        // Ensure directory exists
        if (!is_dir($fullDirectory)) {
            mkdir($fullDirectory, 0755, true);
        }

        file_put_contents($filePath, $content);
        $output->success(sprintf('Created: %s', $filePath));
    }

    /**
     * Load a specific stub by name.
     */
    private function loadStubByName(string $stubName): ?string
    {
        $stubPaths = [
            $this->getBasePath() . '/stubs/' . $stubName,
            dirname(__DIR__, 3) . '/stubs/' . $stubName,
            __DIR__ . '/../../../stubs/' . $stubName,
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
