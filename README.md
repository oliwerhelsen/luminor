# Lumina DDD Framework

A commercial Domain-Driven Design (DDD) boilerplate framework built on top of [Lumina PHP](https://github.com/utopia-php), designed for building REST APIs and domain-driven applications.

## Features

- **Domain-Driven Design** - Built-in abstractions for Entities, Aggregate Roots, Value Objects, Domain Events, and Specifications
- **CQRS Pattern** - Command and Query Responsibility Segregation with dedicated buses
- **Modular Architecture** - Organize your application into self-contained modules
- **Multi-tenancy Support** - Built-in tenant resolution strategies (subdomain, header, path)
- **Repository Pattern** - Clean data access abstractions with filtering, sorting, and pagination
- **HTTP Layer Integration** - Seamless integration with Lumina PHP HTTP library
- **Authentication & Authorization** - Role-based access control with policy support
- **CLI Tools** - Code generators for rapid development
- **Testing Utilities** - In-memory implementations and test helpers

## Requirements

- PHP 8.2 or higher
- Composer

## Installation

```bash
composer require lumina/ddd-framework
```

## Quick Start

```php
<?php

declare(strict_types=1);

use Lumina\DDD\Kernel;

require_once __DIR__ . '/vendor/autoload.php';

$kernel = new Kernel();
$kernel->boot();
```

## Documentation

- [Installation Guide](docs/01-installation.md)
- [Quick Start](docs/02-quick-start.md)
- [Domain Layer](docs/03-domain-layer.md)
- [Application Layer](docs/04-application-layer.md)
- [HTTP Layer](docs/05-http-layer.md)
- [Modules](docs/06-modules.md)
- [Multi-tenancy](docs/07-multitenancy.md)
- [Testing](docs/08-testing.md)

## Project Structure

```
src/
├── Domain/           # Domain layer (Entities, Value Objects, Events)
│   ├── Abstractions/ # Base classes for DDD building blocks
│   ├── Repository/   # Repository interfaces and query abstractions
│   └── Events/       # Domain event system
├── Application/      # Application layer (CQRS, DTOs, Services)
│   ├── Bus/          # Command and Query bus interfaces
│   ├── CQRS/         # Command and Query markers
│   ├── DTO/          # Data Transfer Objects
│   └── Services/     # Application services
├── Infrastructure/   # Infrastructure layer
│   ├── Http/         # HTTP controllers and middleware
│   ├── Persistence/  # Database implementations
│   ├── EventBus/     # Event dispatcher implementations
│   └── Bus/          # Bus implementations
├── Module/           # Module system
├── Multitenancy/     # Multi-tenant support
├── Auth/             # Authentication and authorization
└── Kernel.php        # Application kernel
```

## Development

### Running Tests

```bash
composer test
```

### Static Analysis

```bash
composer analyse
```

### Full Check

```bash
composer check
```

## License

This is proprietary software. See LICENSE for details.
