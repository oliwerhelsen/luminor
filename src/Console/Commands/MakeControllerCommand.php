<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;

/**
 * Command to generate a new Controller class.
 *
 * Creates either a basic API controller or a full CRUD controller
 * with standard REST endpoints.
 */
final class MakeControllerCommand extends AbstractMakeCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:controller')
            ->setDescription('Create a new API controller class')
            ->addArgument('name', [
                'description' => 'The name of the controller (e.g., User, Order)',
                'required' => true,
            ])
            ->addOption('crud', [
                'shortcut' => 'c',
                'description' => 'Create a CRUD controller with standard endpoints',
            ])
            ->addOption('resource', [
                'shortcut' => 'r',
                'description' => 'Alias for --crud',
            ])
            ->addOption('path', [
                'shortcut' => 'p',
                'description' => 'Custom output path (relative to project root)',
            ])
            ->addOption('force', [
                'shortcut' => 'f',
                'description' => 'Overwrite existing file',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function getStubName(): string
    {
        return 'controller.stub';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultDirectory(): string
    {
        return 'src/Infrastructure/Http/Controllers';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultNamespace(): string
    {
        return 'Infrastructure\\Http\\Controllers';
    }

    /**
     * @inheritDoc
     */
    protected function getFileSuffix(): string
    {
        return 'Controller.php';
    }

    /**
     * @inheritDoc
     */
    protected function buildReplacements(Input $input): array
    {
        $name = $input->getArgument('name') ?? '';

        // Remove 'Controller' suffix if provided
        if (str_ends_with($name, 'Controller')) {
            $name = substr($name, 0, -10);
        }

        $controllerName = $this->studly($name);
        $resourceName = $this->snake($controllerName);
        $resourcePlural = $this->plural($resourceName);

        return [
            '{{ controllerName }}' => $controllerName,
            '{{ resourceName }}' => $resourceName,
            '{{ resourcePlural }}' => $resourcePlural,
            '{{ routePrefix }}' => '/' . str_replace('_', '-', $resourcePlural),
            '{{controllerName}}' => $controllerName,
            '{{resourceName}}' => $resourceName,
            '{{resourcePlural}}' => $resourcePlural,
            '{{routePrefix}}' => '/' . str_replace('_', '-', $resourcePlural),
        ];
    }

    /**
     * Get the stub based on options.
     */
    protected function loadStub(): ?string
    {
        // Determine which stub to use
        $stubName = $this->getStubName();

        // Use parent's loadStub method with potentially different stub
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

    /**
     * Override to use CRUD stub if option is set.
     */
    protected function parseClassName(string $name): array
    {
        $result = parent::parseClassName($name);

        // Adjust class name to include Controller suffix
        if (!str_ends_with($result['class'], 'Controller')) {
            // Keep as-is, suffix is added by getFileSuffix
        }

        return $result;
    }
}
