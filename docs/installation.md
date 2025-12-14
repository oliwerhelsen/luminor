---
title: Installation
layout: default
parent: Getting Started
nav_order: 1
description: "Installation and setup guide for Luminor DDD Framework"
---

# Installation

This guide covers the installation and initial setup of the Luminor DDD Framework.

## Requirements

- PHP 8.2 or higher
- Composer 2.x
- One of the supported database systems (MySQL, PostgreSQL, SQLite)

## Quick Installation (Recommended)

The easiest way to create a new Luminor project is using the global installer:

### 1. Install Luminor Globally

```bash
composer global require luminor/luminor
```

Make sure the Composer global bin directory is in your PATH:

```bash
# Add to your ~/.bashrc, ~/.zshrc, or shell config
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

### 2. Create a New Project

```bash
luminor new my-app
```

The installer will interactively guide you through configuration:

- **Project type**: Basic API (flat DDD structure) or Modular Application
- **Database**: None, MySQL, PostgreSQL, or SQLite
- **Multi-tenancy**: Disabled, Header-based, Subdomain-based, or Path-based
- **Git initialization**: Optionally initialize a git repository

You can also use flags for non-interactive installation:

```bash
luminor new my-app --type=basic --database=mysql --multitenancy=none --no-interaction
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
composer require luminor/luminor
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

use Luminor\Kernel;
use Luminor\Http\HttpKernel;

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
chmod +x vendor/bin/luminor
```

Verify installation:

```bash
./vendor/bin/luminor --version
```

## Available CLI Commands

```bash
# List all available commands
vendor/bin/luminor list

# Create a new project (when installed globally)
luminor new my-project

# Start development server
vendor/bin/luminor serve

# Generate code
vendor/bin/luminor make:entity User
vendor/bin/luminor make:command CreateUser
vendor/bin/luminor make:query GetUser
vendor/bin/luminor make:controller UserController
vendor/bin/luminor make:module Billing
```

## Next Steps

- Read the [Quick Start Guide](02-quick-start.md) to create your first entity
- Explore the [Domain Layer Documentation](03-domain-layer.md)
- Set up [Testing](08-testing.md) for your application
