<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Kernel;

/**
 * Command to generate a new test class.
 */
final class MakeTestCommand
{
    /**
     * Get the command name.
     */
    public function getName(): string
    {
        return 'make:test';
    }

    /**
     * Get the command description.
     */
    public function getDescription(): string
    {
        return 'Create a new test class';
    }

    /**
     * Execute the command.
     */
    public function execute(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');

        if ($name === null) {
            $output->writeln('<error>Please provide a test name.</error>');
            $output->writeln('Usage: make:test <name> [--unit]');

            return 1;
        }

        $kernel = Kernel::getInstance();
        if ($kernel === null) {
            $output->writeln('<error>Kernel not initialized.</error>');

            return 1;
        }

        $unit = $input->hasOption('unit');
        $type = $unit ? 'Unit' : 'Feature';

        // Parse namespace and class name
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = array_pop($parts);

        // Ensure name ends with Test
        if (! str_ends_with($className, 'Test')) {
            $className .= 'Test';
        }

        $subNamespace = ! empty($parts) ? '\\' . implode('\\', $parts) : '';

        // Determine output path
        $basePath = $kernel->getBasePath();
        $directory = $basePath . '/tests/' . $type . (! empty($parts) ? '/' . implode('/', $parts) : '');
        $filePath = $directory . '/' . $className . '.php';

        // Create directory if needed
        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        if (file_exists($filePath)) {
            $output->writeln("<error>Test already exists: {$filePath}</error>");

            return 1;
        }

        $stub = $unit
            ? $this->getUnitStub($className, $subNamespace)
            : $this->getFeatureStub($className, $subNamespace);

        file_put_contents($filePath, $stub);

        $output->writeln("<info>Test created successfully:</info> {$filePath}");

        return 0;
    }

    /**
     * Generate a unit test stub.
     */
    private function getUnitStub(string $className, string $subNamespace): string
    {
        $namespace = 'Tests\\Unit' . $subNamespace;

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use PHPUnit\Framework\Attributes\Test;
            use PHPUnit\Framework\TestCase;

            final class {$className} extends TestCase
            {
                #[Test]
                public function it_does_something(): void
                {
                    // Arrange
                    
                    // Act
                    
                    // Assert
                    \$this->assertTrue(true);
                }
            }

            PHP;
    }

    /**
     * Generate a feature test stub.
     */
    private function getFeatureStub(string $className, string $subNamespace): string
    {
        $namespace = 'Tests\\Feature' . $subNamespace;

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Luminor\DDD\Testing\TestCase;
            use PHPUnit\Framework\Attributes\Test;

            final class {$className} extends TestCase
            {
                #[Test]
                public function it_does_something(): void
                {
                    // Arrange
                    
                    // Act
                    
                    // Assert
                    \$this->assertTrue(true);
                }
            }

            PHP;
    }
}
