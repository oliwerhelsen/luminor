<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Kernel;

/**
 * Command to generate a new middleware class.
 */
final class MakeMiddlewareCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:middleware')
            ->setDescription('Create a new middleware class')
            ->addArgument('name', [
                'description' => 'The name of the middleware class',
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
            $output->error('Please provide a middleware name.');
            $output->writeln('Usage: make:middleware <name>');

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
        $subNamespace = ! empty($parts) ? '\\' . implode('\\', $parts) : '';

        // Determine output path
        $basePath = $kernel->getBasePath();
        $directory = $basePath . '/src/Http/Middleware' . (! empty($parts) ? '/' . implode('/', $parts) : '');
        $filePath = $directory . '/' . $className . '.php';

        // Create directory if needed
        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        if (file_exists($filePath)) {
            $output->error("Middleware already exists: {$filePath}");

            return 1;
        }

        $stub = $this->getStub($className, $subNamespace);
        file_put_contents($filePath, $stub);

        $output->success("Middleware created successfully: {$filePath}");

        return 0;
    }

    /**
     * Generate the middleware stub.
     */
    private function getStub(string $className, string $subNamespace): string
    {
        $namespace = 'App\\Http\\Middleware' . $subNamespace;

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Utopia\Http\Request;
            use Utopia\Http\Response;

            /**
             * HTTP middleware.
             */
            final class {$className}
            {
                /**
                 * Handle the request.
                 *
                 * @param Request \$request
                 * @param Response \$response
                 * @param callable \$next
                 * @return Response
                 */
                public function handle(Request \$request, Response \$response, callable \$next): Response
                {
                    // Before middleware logic
                    
                    // Call the next middleware/handler
                    \$response = \$next(\$request, \$response);
                    
                    // After middleware logic
                    
                    return \$response;
                }
            }

            PHP;
    }
}
