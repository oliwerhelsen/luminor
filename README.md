# Luminor PHP Framework

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)]()

A modern, open-source Domain-Driven Design (DDD) framework built on [Symfony HttpFoundation](https://symfony.com/doc/current/components/http_foundation.html), designed for building REST APIs and domain-driven applications. Heavily inspired by [Laravel](https://laravel.com), bringing its elegant developer experience to the world of Domain-Driven Design.

## Features

### Core DDD Features
- **Domain-Driven Design** - Built-in abstractions for Entities, Aggregate Roots, Value Objects, Domain Events, and Specifications
- **CQRS Pattern** - Command and Query Responsibility Segregation with dedicated buses
- **Repository Pattern** - Clean data access abstractions with filtering, sorting, and pagination
- **Modular Architecture** - Organize your application into self-contained modules
- **Multi-tenancy Support** - Built-in tenant resolution strategies (subdomain, header, path)

### Event Sourcing (v2.0+)
- **Event Store** - Persist all domain events with database and in-memory implementations
- **Event-Sourced Aggregates** - Rebuild state from event streams with automatic rehydration
- **Snapshots** - Performance optimization for aggregates with many events
- **Projections** - Build read models from event streams for optimized queries
- **Temporal Queries** - Query events by date range, type, or aggregate

### Infrastructure & Tools
- **HTTP Layer Integration** - Built on Symfony HttpFoundation with support for PHP-FPM, Swoole, and FrankenPHP
- **Authentication & Authorization** - Role-based access control with policy support
- **OpenAPI Documentation** - Automatic API documentation generation with PHP attributes
- **Observability & Metrics** - Track performance with counters, gauges, histograms, and timers
- **CLI Tools** - 30+ console commands for code generation, event management, and more
- **Testing Utilities** - In-memory implementations and test helpers

## Requirements

- PHP 8.2 or higher
- Composer

## Installation

```bash
composer require luminor/luminor

# Global installation to use Luminor CLI tools (luminor new)
composer global require luminor/luminor
```

## Quick Start

```php
<?php

declare(strict_types=1);

use Luminor\DDD\Kernel;

require_once __DIR__ . '/vendor/autoload.php';

$kernel = new Kernel();
$kernel->boot();
```

## Documentation

### Getting Started
- [Installation Guide](docs/installation.md)
- [Quick Start](docs/quick-start.md)
- [Core Concepts](docs/core-concepts.md)
- [Tutorials](docs/tutorials.md)

### Architecture
- [Domain Layer](docs/domain-layer.md)
- [Application Layer](docs/application-layer.md)
- [HTTP Layer](docs/http-layer.md)
- [Modules](docs/modules.md)

### Event Sourcing (v2.0+)
- [Event Sourcing Guide](docs/event-sourcing.md)
- [OpenAPI Documentation](docs/openapi.md)
- [Observability & Metrics](docs/observability.md)

### Features
- [Authentication](docs/AUTHENTICATION.md)
- [Multi-tenancy](docs/multitenancy.md)
- [Testing](docs/testing.md)
- [Best Practices](docs/best-practices.md)

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

## Contributing

We welcome contributions from the community! Here's how you can help:

1. **Fork the repository** and create your branch from `main`
2. **Make your changes** and ensure tests pass
3. **Write tests** for any new functionality
4. **Update documentation** as needed
5. **Submit a pull request**

### Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/luminor.git
cd luminor

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse
```

### Code Style

Please ensure your code follows PSR-12 coding standards and passes static analysis before submitting.

## Community

- **Issues**: Found a bug or have a feature request? [Open an issue](https://github.com/luminor/luminor/issues)
- **Discussions**: Have questions or want to discuss ideas? [Start a discussion](https://github.com/luminor/luminor/discussions)
- **Security**: For security vulnerabilities, please see our [Security Policy](SECURITY.md)

## Roadmap

We're constantly improving Luminor. Check out our [project board](https://github.com/luminor/luminor/projects) to see what's coming next.

## License

This project is open-sourced software licensed under the [MIT License](LICENSE).
