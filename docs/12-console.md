# Console Commands

Lumina provides a powerful CLI interface for running commands, managing queues, and scaffolding code.

## Running Commands

```bash
# List all available commands
php bin/lumina-ddd list

# Run a specific command
php bin/lumina-ddd serve

# Get help for a command
php bin/lumina-ddd serve --help
```

## Built-in Commands

### Development Server

Start the built-in PHP development server:

```bash
php bin/lumina-ddd serve
php bin/lumina-ddd serve --host=0.0.0.0 --port=8080
php bin/lumina-ddd serve --docroot=public
```

| Option | Description | Default |
|--------|-------------|---------|
| `--host`, `-H` | Host address | 127.0.0.1 |
| `--port`, `-p` | Port number | 8000 |
| `--docroot`, `-d` | Document root | public |

### Code Generators

#### Domain Layer

```bash
# Create an entity
php bin/lumina-ddd make:entity User

# Create a repository interface and implementation
php bin/lumina-ddd make:repository User

# Create a value object
php bin/lumina-ddd make:value-object Email
```

#### Application Layer

```bash
# Create a command and handler
php bin/lumina-ddd make:command CreateUser

# Create a query and handler
php bin/lumina-ddd make:query GetUserById
```

#### HTTP Layer

```bash
# Create a controller
php bin/lumina-ddd make:controller UserController

# Create a CRUD controller
php bin/lumina-ddd make:controller UserController --crud

# Create middleware
php bin/lumina-ddd make:middleware RateLimiter
```

#### Infrastructure

```bash
# Create a job class
php bin/lumina-ddd make:job ProcessPayment
php bin/lumina-ddd make:job SyncData --sync

# Create a mailable class
php bin/lumina-ddd make:mail WelcomeEmail
php bin/lumina-ddd make:mail Newsletter --queued

# Create a service provider
php bin/lumina-ddd make:provider PaymentServiceProvider
```

#### Modules

```bash
# Create a complete module structure
php bin/lumina-ddd make:module Billing
```

### Queue Commands

```bash
# Process queue jobs
php bin/lumina-ddd queue:work
php bin/lumina-ddd queue:work --connection=redis --queue=emails

# List failed jobs
php bin/lumina-ddd queue:failed

# Retry failed jobs
php bin/lumina-ddd queue:retry job-id
php bin/lumina-ddd queue:retry --all

# Clear failed jobs
php bin/lumina-ddd queue:flush
php bin/lumina-ddd queue:flush --hours=24
```

## Creating Custom Commands

### Extending AbstractCommand

```php
<?php

namespace App\Console\Commands;

use Lumina\DDD\Console\Commands\AbstractCommand;
use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;

final class GreetCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('greet')
            ->setDescription('Greet someone')
            ->addArgument('name', [
                'description' => 'The name to greet',
                'required' => true,
            ])
            ->addOption('yell', [
                'shortcut' => 'y',
                'description' => 'Yell the greeting',
            ]);
    }

    protected function handle(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');
        $greeting = "Hello, {$name}!";

        if ($input->getOption('yell')) {
            $greeting = strtoupper($greeting);
        }

        $output->success($greeting);
        return 0;
    }
}
```

### Registering Commands

Register your command in a service provider:

```php
use App\Console\Commands\GreetCommand;
use Lumina\DDD\Console\Application;

public function boot(ContainerInterface $container): void
{
    $console = $container->get(Application::class);
    $console->register(new GreetCommand());
}
```

## Input Handling

### Arguments

```php
// Define arguments
$this->addArgument('name', [
    'description' => 'The user name',
    'required' => true,
    'default' => 'Guest',
]);

// Access in handle()
$name = $input->getArgument('name');
```

### Options

```php
// Define options
$this->addOption('format', [
    'shortcut' => 'f',
    'description' => 'Output format',
    'default' => 'json',
]);

$this->addOption('verbose', [
    'shortcut' => 'v',
    'description' => 'Enable verbose output',
]);

// Access in handle()
$format = $input->getOption('format');  // Returns value or default
$verbose = $input->hasOption('verbose'); // Boolean check
```

## Output Formatting

The `Output` class provides styled output methods:

```php
// Standard output
$output->writeln('Regular message');
$output->newLine();

// Styled messages
$output->info('Information message');      // Blue
$output->success('Success message');       // Green
$output->warning('Warning message');       // Yellow
$output->error('Error message');           // Red
$output->comment('Comment message');       // Gray

// With formatting tags
$output->writeln('<info>Blue text</info>');
$output->writeln('<comment>Yellow text</comment>');
$output->writeln('<error>Red text</error>');
```

## Exit Codes

Return appropriate exit codes from your commands:

```php
protected function handle(Input $input, Output $output): int
{
    if ($error) {
        $output->error('Something went wrong');
        return 1; // Non-zero indicates failure
    }

    $output->success('Done!');
    return 0; // Zero indicates success
}
```

## Command Groups

Commands are automatically grouped by their prefix:

```
Available commands:
  list              List all commands
  serve             Start the development server

make
  make:command      Create a new command
  make:controller   Create a new controller
  make:entity       Create a new entity
  make:job          Create a new job
  make:mail         Create a new mailable
  make:middleware   Create a new middleware
  make:module       Create a new module
  make:provider     Create a new service provider
  make:query        Create a new query
  make:repository   Create a new repository

queue
  queue:failed      List failed jobs
  queue:flush       Clear failed jobs
  queue:retry       Retry failed jobs
  queue:work        Process queue jobs
```

## Dependency Injection

Commands that need container access can implement the `setContainer` method:

```php
final class DatabaseCommand extends AbstractCommand
{
    private ?ContainerInterface $container = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    protected function handle(Input $input, Output $output): int
    {
        $db = $this->container->get(Connection::class);
        // Use the database connection...
        return 0;
    }
}
```

The Console Application automatically injects the container for commands with this method.
