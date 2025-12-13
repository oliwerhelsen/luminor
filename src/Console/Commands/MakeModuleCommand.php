<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;

/**
 * Command to generate a new Module structure.
 *
 * Creates a complete module with domain, application, and
 * infrastructure layers following the modular architecture pattern.
 */
final class MakeModuleCommand extends AbstractMakeCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:module')
            ->setDescription('Create a new application module with complete structure')
            ->addArgument('name', [
                'description' => 'The name of the module (e.g., User, Order, Inventory)',
                'required' => true,
            ])
            ->addOption('minimal', [
                'shortcut' => 'm',
                'description' => 'Create a minimal module without example entities',
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
        return 'module.stub';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultDirectory(): string
    {
        return 'src/Modules';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultNamespace(): string
    {
        return 'Modules';
    }

    /**
     * @inheritDoc
     */
    protected function getFileSuffix(): string
    {
        return 'Module.php';
    }

    /**
     * @inheritDoc
     */
    protected function buildReplacements(Input $input): array
    {
        $name = $input->getArgument('name') ?? '';

        // Remove 'Module' suffix if provided
        if (str_ends_with($name, 'Module')) {
            $name = substr($name, 0, -6);
        }

        $moduleName = $this->studly($name);

        return [
            '{{ moduleName }}' => $moduleName,
            '{{ moduleNameLower }}' => strtolower($moduleName),
            '{{ moduleNameSnake }}' => $this->snake($moduleName),
            '{{moduleName}}' => $moduleName,
            '{{moduleNameLower}}' => strtolower($moduleName),
            '{{moduleNameSnake}}' => $this->snake($moduleName),
        ];
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

        // Remove 'Module' suffix if provided
        if (str_ends_with($name, 'Module')) {
            $name = substr($name, 0, -6);
        }

        $moduleName = $this->studly($name);
        $basePath = $this->getBasePath();
        $modulePath = $basePath . DIRECTORY_SEPARATOR . 'src/Modules/' . $moduleName;

        // Create module directory structure
        $directories = [
            '',
            '/Domain',
            '/Domain/Entities',
            '/Domain/ValueObjects',
            '/Domain/Events',
            '/Domain/Repository',
            '/Domain/Exceptions',
            '/Application',
            '/Application/Commands',
            '/Application/Queries',
            '/Application/Handlers',
            '/Application/DTOs',
            '/Application/Services',
            '/Infrastructure',
            '/Infrastructure/Persistence',
            '/Infrastructure/Http',
            '/Infrastructure/Http/Controllers',
        ];

        $output->info(sprintf('Creating module: %s', $moduleName));
        $output->newLine();

        foreach ($directories as $dir) {
            $fullPath = $modulePath . $dir;
            if (! is_dir($fullPath)) {
                if (mkdir($fullPath, 0o755, true)) {
                    $output->line(sprintf('  Created directory: %s', $dir ?: '/'));
                }
            }
        }

        // Create the main module class
        $moduleFile = $modulePath . '/' . $moduleName . 'Module.php';
        if (! file_exists($moduleFile) || $input->hasOption('force')) {
            $stubContent = $this->loadStub();
            if ($stubContent !== null) {
                $namespace = 'App\\Modules\\' . $moduleName;
                $replacements = [
                    '{{ namespace }}' => $namespace,
                    '{{ class }}' => $moduleName . 'Module',
                    '{{ moduleName }}' => $moduleName,
                    '{{ moduleNameLower }}' => strtolower($moduleName),
                    '{{ moduleNameSnake }}' => $this->snake($moduleName),
                    '{{namespace}}' => $namespace,
                    '{{class}}' => $moduleName . 'Module',
                    '{{moduleName}}' => $moduleName,
                    '{{moduleNameLower}}' => strtolower($moduleName),
                    '{{moduleNameSnake}}' => $this->snake($moduleName),
                ];

                $content = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $stubContent,
                );

                file_put_contents($moduleFile, $content);
                $output->success(sprintf('Created: %s', $moduleFile));
            }
        }

        // Create additional module files unless minimal
        if (! $input->hasOption('minimal')) {
            $this->createModuleServiceProvider($modulePath, $moduleName, $input, $output);
            $this->createModuleRoutes($modulePath, $moduleName, $input, $output);
        }

        // Create .gitkeep files in empty directories
        $this->createGitKeepFiles($modulePath, $directories);

        $output->newLine();
        $output->success(sprintf('Module %s created successfully!', $moduleName));

        return 0;
    }

    /**
     * Create the module's service provider.
     */
    private function createModuleServiceProvider(
        string $modulePath,
        string $moduleName,
        Input $input,
        Output $output,
    ): void {
        $filePath = $modulePath . '/' . $moduleName . 'ServiceProvider.php';

        if (file_exists($filePath) && ! $input->hasOption('force')) {
            return;
        }

        $namespace = 'App\\Modules\\' . $moduleName;

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Luminor\\DDD\\Container\\AbstractServiceProvider;
            use Luminor\\DDD\\Container\\ContainerInterface;

            /**
             * Service provider for the {$moduleName} module.
             *
             * Registers module dependencies and bindings.
             */
            final class {$moduleName}ServiceProvider extends AbstractServiceProvider
            {
                /**
                 * @inheritDoc
                 */
                public function register(ContainerInterface \$container): void
                {
                    // Register repository implementations
                    // \$container->bind(
                    //     {$moduleName}RepositoryInterface::class,
                    //     {$moduleName}Repository::class
                    // );

                    // Register services
                    // \$container->singleton({$moduleName}Service::class);
                }

                /**
                 * @inheritDoc
                 */
                public function boot(ContainerInterface \$container): void
                {
                    // Boot module services
                }

                /**
                 * @inheritDoc
                 */
                public function provides(): array
                {
                    return [
                        // List of services this provider registers
                    ];
                }
            }

            PHP;

        file_put_contents($filePath, $content);
        $output->success(sprintf('Created: %s', $filePath));
    }

    /**
     * Create the module's routes file.
     */
    private function createModuleRoutes(
        string $modulePath,
        string $moduleName,
        Input $input,
        Output $output,
    ): void {
        $filePath = $modulePath . '/routes.php';

        if (file_exists($filePath) && ! $input->hasOption('force')) {
            return;
        }

        $routePrefix = strtolower($moduleName);
        $resourcePlural = $this->plural($routePrefix);

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            /**
             * Routes for the {$moduleName} module.
             *
             * Define your module's HTTP routes here.
             */

            use Utopia\\Http\\Http;

            // Example routes for the {$moduleName} module
            // Uncomment and customize as needed

            // \$http = Http::getInstance();

            // List all
            // \$http->get('/{$resourcePlural}')
            //     ->inject('request')
            //     ->inject('response')
            //     ->action(function (\$request, \$response) {
            //         // Handle list request
            //     });

            // Get one
            // \$http->get('/{$resourcePlural}/:id')
            //     ->param('id', '', 'string', 'Resource ID')
            //     ->inject('request')
            //     ->inject('response')
            //     ->action(function (string \$id, \$request, \$response) {
            //         // Handle get request
            //     });

            // Create
            // \$http->post('/{$resourcePlural}')
            //     ->inject('request')
            //     ->inject('response')
            //     ->action(function (\$request, \$response) {
            //         // Handle create request
            //     });

            // Update
            // \$http->put('/{$resourcePlural}/:id')
            //     ->param('id', '', 'string', 'Resource ID')
            //     ->inject('request')
            //     ->inject('response')
            //     ->action(function (string \$id, \$request, \$response) {
            //         // Handle update request
            //     });

            // Delete
            // \$http->delete('/{$resourcePlural}/:id')
            //     ->param('id', '', 'string', 'Resource ID')
            //     ->inject('request')
            //     ->inject('response')
            //     ->action(function (string \$id, \$request, \$response) {
            //         // Handle delete request
            //     });

            PHP;

        file_put_contents($filePath, $content);
        $output->success(sprintf('Created: %s', $filePath));
    }

    /**
     * Create .gitkeep files in empty directories.
     *
     * @param array<string> $directories
     */
    private function createGitKeepFiles(string $modulePath, array $directories): void
    {
        $keepDirectories = [
            '/Domain/Entities',
            '/Domain/ValueObjects',
            '/Domain/Events',
            '/Domain/Repository',
            '/Domain/Exceptions',
            '/Application/Commands',
            '/Application/Queries',
            '/Application/Handlers',
            '/Application/DTOs',
            '/Application/Services',
            '/Infrastructure/Persistence',
            '/Infrastructure/Http/Controllers',
        ];

        foreach ($keepDirectories as $dir) {
            $gitkeepPath = $modulePath . $dir . '/.gitkeep';
            if (! file_exists($gitkeepPath)) {
                touch($gitkeepPath);
            }
        }
    }
}
