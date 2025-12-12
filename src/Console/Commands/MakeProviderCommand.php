<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;
use Lumina\DDD\Kernel;

/**
 * Command to generate a new service provider class.
 */
final class MakeProviderCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:provider')
            ->setDescription('Create a new service provider class')
            ->addArgument('name', [
                'description' => 'The name of the service provider class',
                'required' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');

        if ($name === null) {
            $output->error('Please provide a provider name.');
            $output->writeln('Usage: make:provider <name>');
            return 1;
        }

        $kernel = Kernel::getInstance();
        if ($kernel === null) {
            $output->error('Kernel not initialized.');
            return 1;
        }

        // Parse namespace and class name
        $parts = explode('/', str_replace('\\', '/', (string) $name));
        $className = array_pop($parts);
        
        // Ensure name ends with Provider
        if (!str_ends_with($className, 'Provider')) {
            $className .= 'ServiceProvider';
        }
        
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';

        // Determine output path
        $basePath = $kernel->getBasePath();
        $directory = $basePath . '/src/Providers' . (!empty($parts) ? '/' . implode('/', $parts) : '');
        $filePath = $directory . '/' . $className . '.php';

        // Create directory if needed
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($filePath)) {
            $output->error("Provider already exists: {$filePath}");
            return 1;
        }

        $stub = $this->getStub($className, $subNamespace);
        file_put_contents($filePath, $stub);

        $output->success("Provider created successfully: {$filePath}");
        $output->newLine();
        $output->comment("Don't forget to register this provider in your config/framework.php:");
        $output->writeln("  'providers' => [");
        $output->writeln("      App\\Providers\\{$className}::class,");
        $output->writeln("  ],");

        return 0;
    }

    /**
     * Generate the provider stub.
     */
    private function getStub(string $className, string $subNamespace): string
    {
        $namespace = 'App\\Providers' . $subNamespace;

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Lumina\DDD\Container\AbstractServiceProvider;
use Lumina\DDD\Container\ContainerInterface;

/**
 * Service provider for registering application services.
 */
final class {$className} extends AbstractServiceProvider
{
    /**
     * Register services into the container.
     */
    public function register(ContainerInterface \$container): void
    {
        // Bind interfaces to implementations
        // \$container->bind(SomeInterface::class, SomeImplementation::class);
        
        // Register singletons
        // \$container->singleton(SomeService::class, function (ContainerInterface \$c) {
        //     return new SomeService(\$c->get(Dependency::class));
        // });
    }

    /**
     * Bootstrap services after all providers are registered.
     */
    public function boot(ContainerInterface \$container): void
    {
        // Perform any bootstrapping logic here
    }
}

PHP;
    }
}
