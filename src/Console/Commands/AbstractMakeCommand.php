<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;

/**
 * Base class for code generation commands.
 *
 * Provides common functionality for all 'make' commands including
 * stub loading, placeholder replacement, and file writing.
 */
abstract class AbstractMakeCommand extends AbstractCommand
{
    /**
     * Get the stub file name for this command.
     */
    abstract protected function getStubName(): string;

    /**
     * Get the default output directory relative to the base path.
     */
    abstract protected function getDefaultDirectory(): string;

    /**
     * Get the file suffix (e.g., '.php').
     */
    protected function getFileSuffix(): string
    {
        return '.php';
    }

    /**
     * Build additional placeholder replacements.
     *
     * @param Input $input The command input
     *
     * @return array<string, string> Placeholder => Value pairs
     */
    protected function buildReplacements(Input $input): array
    {
        return [];
    }

    /**
     * Get the base path for file generation.
     */
    protected function getBasePath(): string
    {
        return getcwd() ?: '.';
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');
        if ($name === null) {
            $output->error('Name argument is required.');

            return 1;
        }

        // Parse namespace and class name
        $parts = $this->parseClassName($name);
        $className = $parts['class'];
        $namespace = $parts['namespace'];
        $subDirectory = $parts['directory'];

        // Build output path
        $directory = $this->resolveDirectory($input, $subDirectory);
        $filePath = $directory . DIRECTORY_SEPARATOR . $className . $this->getFileSuffix();

        // Check if file already exists
        if (file_exists($filePath) && ! $input->hasOption('force')) {
            $output->error(sprintf('File already exists: %s', $filePath));
            $output->line('Use --force to overwrite.');

            return 1;
        }

        // Load and process stub
        $stubContent = $this->loadStub();
        if ($stubContent === null) {
            $output->error(sprintf('Stub file not found: %s', $this->getStubName()));

            return 1;
        }

        // Build replacements
        $replacements = array_merge(
            [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $className,
                '{{namespace}}' => $namespace,
                '{{class}}' => $className,
            ],
            $this->buildReplacements($input),
        );

        // Process the stub
        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stubContent,
        );

        // Ensure directory exists
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0o755, true)) {
                $output->error(sprintf('Failed to create directory: %s', $directory));

                return 1;
            }
        }

        // Write file
        if (file_put_contents($filePath, $content) === false) {
            $output->error(sprintf('Failed to write file: %s', $filePath));

            return 1;
        }

        $output->success(sprintf('Created: %s', $filePath));

        return 0;
    }

    /**
     * Parse a class name with optional namespace.
     *
     * Supports formats like:
     * - "User" => class: User, namespace: App\{default}
     * - "Domain/User" => class: User, namespace: App\{default}\Domain
     * - "My\Namespace\User" => class: User, namespace: My\Namespace
     *
     * @return array{class: string, namespace: string, directory: string}
     */
    protected function parseClassName(string $name): array
    {
        // Replace forward slashes with backslashes for namespace
        $name = str_replace('/', '\\', $name);

        // Check if it contains namespace separator
        if (str_contains($name, '\\')) {
            $parts = explode('\\', $name);
            $className = array_pop($parts);
            $relativeNamespace = implode('\\', $parts);
            $directory = str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);

            // If starts with 'App\', use as-is, otherwise prepend default namespace
            if (str_starts_with($relativeNamespace, 'App\\') || $relativeNamespace === 'App') {
                $namespace = $relativeNamespace;
            } else {
                $namespace = 'App\\' . $this->getDefaultNamespace() . '\\' . $relativeNamespace;
            }
        } else {
            $className = $name;
            $namespace = 'App\\' . $this->getDefaultNamespace();
            $directory = '';
        }

        return [
            'class' => $className,
            'namespace' => $namespace,
            'directory' => $directory,
        ];
    }

    /**
     * Get the default namespace segment for this command.
     */
    protected function getDefaultNamespace(): string
    {
        return 'Domain';
    }

    /**
     * Resolve the output directory.
     */
    protected function resolveDirectory(Input $input, string $subDirectory): string
    {
        $basePath = $this->getBasePath();

        // Check for custom path option
        $customPath = $input->getOption('path');
        if (is_string($customPath) && $customPath !== '') {
            $directory = $basePath . DIRECTORY_SEPARATOR . $customPath;
        } else {
            $directory = $basePath . DIRECTORY_SEPARATOR . $this->getDefaultDirectory();
        }

        if ($subDirectory !== '') {
            $directory .= DIRECTORY_SEPARATOR . $subDirectory;
        }

        return $directory;
    }

    /**
     * Load a stub file.
     */
    protected function loadStub(): ?string
    {
        $stubPaths = [
            // User's custom stubs
            $this->getBasePath() . '/stubs/' . $this->getStubName(),
            // Framework's default stubs
            dirname(__DIR__, 3) . '/stubs/' . $this->getStubName(),
            __DIR__ . '/../../../stubs/' . $this->getStubName(),
        ];

        foreach ($stubPaths as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);

                return $content !== false ? $content : null;
            }
        }

        return null;
    }

    /**
     * Convert a string to StudlyCase.
     */
    protected function studly(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return str_replace(' ', '', $value);
    }

    /**
     * Convert a string to camelCase.
     */
    protected function camel(string $value): string
    {
        return lcfirst($this->studly($value));
    }

    /**
     * Convert a string to snake_case.
     */
    protected function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        return strtolower($value ?? $value);
    }

    /**
     * Get the plural form of a word (simple implementation).
     */
    protected function plural(string $value): string
    {
        // Simple pluralization rules
        if (str_ends_with($value, 'y')) {
            return substr($value, 0, -1) . 'ies';
        }
        if (str_ends_with($value, 's') || str_ends_with($value, 'x') ||
            str_ends_with($value, 'ch') || str_ends_with($value, 'sh')) {
            return $value . 'es';
        }

        return $value . 's';
    }
}
