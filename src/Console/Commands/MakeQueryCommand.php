<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;

/**
 * Command to generate a new CQRS Query and Handler.
 *
 * Creates a query class for the CQRS pattern along with
 * its corresponding handler.
 */
final class MakeQueryCommand extends AbstractMakeCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:query')
            ->setDescription('Create a new CQRS query and handler')
            ->addArgument('name', [
                'description' => 'The name of the query (e.g., GetUserById, FindActiveOrders)',
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
        return 'query.stub';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultDirectory(): string
    {
        return 'src/Application/Queries';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultNamespace(): string
    {
        return 'Application\\Queries';
    }

    /**
     * @inheritDoc
     */
    protected function getFileSuffix(): string
    {
        return 'Query.php';
    }

    /**
     * @inheritDoc
     */
    protected function buildReplacements(Input $input): array
    {
        $name = $input->getArgument('name') ?? '';

        // Remove 'Query' suffix if provided
        if (str_ends_with($name, 'Query')) {
            $name = substr($name, 0, -5);
        }

        $queryName = $this->studly($name);

        return [
            '{{ queryName }}' => $queryName,
            '{{queryName}}' => $queryName,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        // Create the query
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
     * Create the query handler.
     */
    private function createHandler(Input $input, Output $output): void
    {
        $name = $input->getArgument('name') ?? '';

        // Remove 'Query' suffix if provided
        if (str_ends_with($name, 'Query')) {
            $name = substr($name, 0, -5);
        }

        $queryName = $this->studly($name);
        $handlerName = $queryName . 'QueryHandler';

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
            $output->warning('Handler stub file not found: query-handler.stub');
            return;
        }

        $namespace = 'App\\Application\\Handlers';
        $queryNamespace = 'App\\Application\\Queries';

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $handlerName,
            '{{ queryName }}' => $queryName,
            '{{ queryNamespace }}' => $queryNamespace,
            '{{ queryVariable }}' => $this->camel($queryName) . 'Query',
            '{{namespace}}' => $namespace,
            '{{class}}' => $handlerName,
            '{{queryName}}' => $queryName,
            '{{queryNamespace}}' => $queryNamespace,
            '{{queryVariable}}' => $this->camel($queryName) . 'Query',
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
            $this->getBasePath() . '/stubs/query-handler.stub',
            dirname(__DIR__, 3) . '/stubs/query-handler.stub',
            __DIR__ . '/../../../stubs/query-handler.stub',
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
