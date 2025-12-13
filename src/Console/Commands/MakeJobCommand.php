<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Kernel;

/**
 * Command to generate a new queue job class.
 */
final class MakeJobCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:job')
            ->setDescription('Create a new job class')
            ->addArgument('name', [
                'description' => 'The name of the job class',
                'required' => true,
            ])
            ->addOption('sync', [
                'description' => 'Create a synchronous job (not queued)',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');

        if ($name === null) {
            $output->error('Please provide a job name.');
            $output->writeln('Usage: make:job <name> [--sync]');
            return 1;
        }

        $kernel = Kernel::getInstance();
        if ($kernel === null) {
            $output->error('Kernel not initialized.');
            return 1;
        }

        $sync = $input->hasOption('sync') && $input->getOption('sync') !== false;
        
        // Parse namespace and class name
        $parts = explode('/', str_replace('\\', '/', (string) $name));
        $className = array_pop($parts);
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';

        // Determine output path
        $basePath = $kernel->getBasePath();
        $directory = $basePath . '/src/Jobs' . (!empty($parts) ? '/' . implode('/', $parts) : '');
        $filePath = $directory . '/' . $className . '.php';

        // Create directory if needed
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($filePath)) {
            $output->error("Job already exists: {$filePath}");
            return 1;
        }

        $stub = $this->getStub($className, $subNamespace, $sync);
        file_put_contents($filePath, $stub);

        $output->success("Job created successfully: {$filePath}");

        return 0;
    }

    /**
     * Generate the job stub.
     */
    private function getStub(string $className, string $subNamespace, bool $sync): string
    {
        $namespace = 'App\\Jobs' . $subNamespace;
        
        if ($sync) {
            return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Luminor\DDD\Queue\Job;

/**
 * Synchronous job that runs immediately.
 */
final class {$className} extends Job
{
    public function __construct(
        // Add your job data here
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Implement job logic
    }

    /**
     * Handle job failure.
     */
    public function failed(\\Throwable \$exception): void
    {
        // Log or handle the failure
    }
}

PHP;
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Luminor\DDD\Queue\Job;
use Luminor\DDD\Queue\ShouldQueue;

/**
 * Queued job that runs in the background.
 */
final class {$className} extends Job implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int \$tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int \$backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     */
    public int \$timeout = 120;

    public function __construct(
        // Add your job data here
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Implement job logic
    }

    /**
     * Handle job failure.
     */
    public function failed(\\Throwable \$exception): void
    {
        // Log or handle the failure
    }
}

PHP;
    }
}
