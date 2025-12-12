<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;
use Lumina\DDD\Kernel;

/**
 * Command to generate a new mailable class.
 */
final class MakeMailCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:mail')
            ->setDescription('Create a new mailable class')
            ->addArgument('name', [
                'description' => 'The name of the mailable class',
                'required' => true,
            ])
            ->addOption('queued', [
                'shortcut' => 'q',
                'description' => 'Create a queued mailable',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');

        if ($name === null) {
            $output->error('Please provide a mail name.');
            $output->writeln('Usage: make:mail <name> [--queued]');
            return 1;
        }

        $kernel = Kernel::getInstance();
        if ($kernel === null) {
            $output->error('Kernel not initialized.');
            return 1;
        }

        $queued = $input->hasOption('queued') && $input->getOption('queued') !== false;
        
        // Parse namespace and class name
        $parts = explode('/', str_replace('\\', '/', (string) $name));
        $className = array_pop($parts);
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';

        // Determine output path
        $basePath = $kernel->getBasePath();
        $directory = $basePath . '/src/Mail' . (!empty($parts) ? '/' . implode('/', $parts) : '');
        $filePath = $directory . '/' . $className . '.php';

        // Create directory if needed
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($filePath)) {
            $output->error("Mail already exists: {$filePath}");
            return 1;
        }

        $stub = $this->getStub($className, $subNamespace, $queued);
        file_put_contents($filePath, $stub);

        $output->success("Mail created successfully: {$filePath}");

        return 0;
    }

    /**
     * Generate the mailable stub.
     */
    private function getStub(string $className, string $subNamespace, bool $queued): string
    {
        $namespace = 'App\\Mail' . $subNamespace;
        
        $useStatements = "use Lumina\\DDD\\Mail\\Mailable;\nuse Lumina\\DDD\\Mail\\Message;";
        $implements = '';
        
        if ($queued) {
            $useStatements .= "\nuse Lumina\\DDD\\Mail\\ShouldQueue;";
            $implements = ' implements ShouldQueue';
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$useStatements}

final class {$className} extends Mailable{$implements}
{
    public function __construct(
        // Add your mailable data here
    ) {}

    /**
     * Build the message.
     */
    public function build(): Message
    {
        return \$this->subject('Your Subject')
            ->html(\$this->renderHtml())
            ->text(\$this->renderText());
    }

    /**
     * Render the HTML content.
     */
    private function renderHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Email</title>
</head>
<body>
    <h1>Hello!</h1>
    <p>This is your email content.</p>
</body>
</html>
HTML;
    }

    /**
     * Render the plain text content.
     */
    private function renderText(): string
    {
        return "Hello!\n\nThis is your email content.";
    }
}

PHP;
    }
}
