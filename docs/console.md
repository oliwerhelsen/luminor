---
title: Console Commands
layout: default
parent: Features
nav_order: 7
description: "CLI interface, code generators, and custom commands"
---

# Console Commands

Luminor provides a powerful CLI interface for running commands, managing queues, and scaffolding code.

## Running Commands

```bash
# List all available commands
vendor/bin/luminor list

# Run a specific command
vendor/bin/luminor serve

# Get help for a command
vendor/bin/luminor serve --help
```

## Built-in Commands

### Project Scaffolding

Create a new Luminor project with interactive configuration:

```bash
# Interactive mode (recommended)
luminor new my-app

# Non-interactive with options
luminor new my-app --type=basic --database=mysql --multitenancy=none

# Skip composer install
luminor new my-app --no-install

# Overwrite existing directory
luminor new my-app --force
```

| Option             | Description                                | Default |
| ------------------ | ------------------------------------------ | ------- |
| `--vendor`         | Vendor name for composer.json              | (asked) |
| `--namespace`      | Root PHP namespace                         | App     |
| `--type`, `-t`     | Project type: basic or modular             | basic   |
| `--database`, `-d` | Database: none, mysql, postgres, or sqlite | none    |
| `--multitenancy`   | Strategy: none, header, subdomain, or path | none    |
| `--git`            | Initialize git repository                  | true    |
| `--no-git`         | Skip git initialization                    |         |
| `--no-install`     | Skip composer install                      |         |
| `--no-interaction` | Do not ask any interactive questions       |         |
| `--force`, `-f`    | Overwrite existing directory               |         |

### Development Server

Start the built-in PHP development server:

```bash
vendor/bin/luminor serve
vendor/bin/luminor serve --host=0.0.0.0 --port=8080
vendor/bin/luminor serve --docroot=public
```

| Option            | Description   | Default   |
| ----------------- | ------------- | --------- |
| `--host`, `-H`    | Host address  | 127.0.0.1 |
| `--port`, `-p`    | Port number   | 8000      |
| `--docroot`, `-d` | Document root | public    |

### Code Generators

#### Domain Layer

```bash
# Create an entity
vendor/bin/luminor make:entity User

# Create a repository interface and implementation
vendor/bin/luminor make:repository User

# Create a value object
vendor/bin/luminor make:value-object Email
```

#### Application Layer

```bash
# Create a command and handler
vendor/bin/luminor make:command CreateUser

# Create a query and handler
vendor/bin/luminor make:query GetUserById
```

#### HTTP Layer

```bash
# Create a controller
vendor/bin/luminor make:controller UserController

# Create a CRUD controller
vendor/bin/luminor make:controller UserController --crud

# Create middleware
vendor/bin/luminor make:middleware RateLimiter
```

#### Infrastructure

```bash
# Create a job class
vendor/bin/luminor make:job ProcessPayment
vendor/bin/luminor make:job SyncData --sync

# Create a mailable class
vendor/bin/luminor make:mail WelcomeEmail
vendor/bin/luminor make:mail Newsletter --queued

# Create a service provider
vendor/bin/luminor make:provider PaymentServiceProvider
```

#### Modules

```bash
# Create a complete module structure
vendor/bin/luminor make:module Billing
```

### Queue Commands

```bash
# Process queue jobs
vendor/bin/luminor queue:work
vendor/bin/luminor queue:work --connection=redis --queue=emails

# List failed jobs
vendor/bin/luminor queue:failed

# Retry failed jobs
vendor/bin/luminor queue:retry job-id
vendor/bin/luminor queue:retry --all

# Clear failed jobs
vendor/bin/luminor queue:flush
vendor/bin/luminor queue:flush --hours=24
```

## Creating Custom Commands

### Extending AbstractCommand

```php
<?php

namespace App\Console\Commands;

use Luminor\DDD\Console\Commands\AbstractCommand;
use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;

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
use Luminor\DDD\Console\Application;

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
