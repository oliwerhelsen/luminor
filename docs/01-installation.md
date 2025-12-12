# Installation

This guide covers the installation and initial setup of the Lumina DDD Framework.

## Requirements

- PHP 8.2 or higher
- Composer 2.x
- One of the supported database systems (MySQL, PostgreSQL, SQLite)

## Installation via Composer

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
chmod +x vendor/bin/lumina-ddd
```

Verify installation:

```bash
./vendor/bin/lumina-ddd --version
```

## Next Steps

- Read the [Quick Start Guide](02-quick-start.md) to create your first entity
- Explore the [Domain Layer Documentation](03-domain-layer.md)
- Set up [Testing](08-testing.md) for your application
