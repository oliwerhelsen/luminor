# Installation

This guide covers the installation and initial setup of the Lumina DDD Framework.

## Requirements

- PHP 8.2 or higher
- Composer 2.x
- One of the supported database systems (MySQL, PostgreSQL, SQLite)

## Quick Installation (Recommended)

The easiest way to create a new Lumina project is using the global installer:

### 1. Install Lumina Globally

```bash
composer global require lumina/ddd-framework
```

Make sure the Composer global bin directory is in your PATH:

```bash
# Add to your ~/.bashrc, ~/.zshrc, or shell config
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

### 2. Create a New Project

```bash
lumina new my-app
```

The installer will interactively guide you through configuration:

- **Project type**: Basic API (flat DDD structure) or Modular Application
- **Database**: None, MySQL, PostgreSQL, or SQLite
- **Multi-tenancy**: Disabled, Header-based, Subdomain-based, or Path-based
- **Git initialization**: Optionally initialize a git repository

You can also use flags for non-interactive installation:

```bash
lumina new my-app --type=basic --database=mysql --multitenancy=none --no-interaction
```

### 3. Start Development

```bash
cd my-app
composer serve
```

Visit `http://localhost:8000` to see your application running.

## Manual Installation

If you prefer to set up the framework manually:

```bash
composer require lumina/ddd-framework
```

## Project Structure

After installation, your project should follow this recommended structure:

```
your-project/
├── config/
│   └── framework.php          # Framework configuration
├── src/
│   ├── Domain/               # Domain layer
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   ├── Events/
│   │   └── Repository/
│   ├── Application/          # Application layer
│   │   ├── Commands/
│   │   ├── Queries/
│   │   ├── Handlers/
│   │   └── Services/
│   ├── Infrastructure/       # Infrastructure layer
│   │   ├── Persistence/
│   │   └── Http/
│   └── Modules/              # Optional modular structure
├── tests/
├── public/
│   └── index.php             # Entry point
└── composer.json
```

## Basic Configuration

Create a `config/framework.php` file:

```php
<?php

return [
    'app' => [
        'name' => 'My Application',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
    ],

    'multitenancy' => [
        'enabled' => false,
        'strategy' => 'header', // header, subdomain, or path
    ],

    'modules' => [
        'autoload' => true,
        'path' => __DIR__ . '/../src/Modules',
    ],
];
```

## Entry Point Setup

Create `public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Lumina\DDD\Kernel;
use Utopia\Http\Http;

// Bootstrap the framework
$kernel = new Kernel(__DIR__ . '/..');
$kernel->boot();

// Get the HTTP instance
$http = Http::getInstance();

// Define your routes here or load from modules
// ...

// Run the application
$http->start();
```

## CLI Setup

Make the CLI tool executable:

```bash
chmod +x vendor/bin/lumina
```

Verify installation:

```bash
./vendor/bin/lumina --version
```

## Available CLI Commands

```bash
# List all available commands
vendor/bin/lumina list

# Create a new project (when installed globally)
lumina new my-project

# Start development server
vendor/bin/lumina serve

# Generate code
vendor/bin/lumina make:entity User
vendor/bin/lumina make:command CreateUser
vendor/bin/lumina make:query GetUser
vendor/bin/lumina make:controller UserController
vendor/bin/lumina make:module Billing
```

## Next Steps

- Read the [Quick Start Guide](02-quick-start.md) to create your first entity
- Explore the [Domain Layer Documentation](03-domain-layer.md)
- Set up [Testing](08-testing.md) for your application
