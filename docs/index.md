---
title: Home
layout: home
nav_order: 1
description: "Luminor - A modern Domain-Driven Design (DDD) framework for PHP"
permalink: /
---

# Luminor Framework

{: .fs-9 }

A modern, open-source Domain-Driven Design (DDD) framework for PHP, built on Symfony HttpFoundation.
{: .fs-6 .fw-300 }

[Get Started](getting-started){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/luminor-php/luminor){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## Why Luminor?

Luminor brings the elegance of Laravel's developer experience to the world of Domain-Driven Design. If you're building REST APIs or domain-driven applications and want clean architecture without the complexity, Luminor is for you.

### Key Features

|                               |                                                                                                                                                   |
| :---------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------ |
| **🏛️ DDD-First Architecture** | Built-in abstractions for Entities, Aggregate Roots, Value Objects, Domain Events, and Specifications. Design your domain model the right way.    |
| **⚡ CQRS Pattern**           | Command and Query Responsibility Segregation out of the box. Separate your read and write operations for cleaner, more scalable code.             |
| **📦 Modular Architecture**   | Organize your application into self-contained modules with bounded contexts. Perfect for large-scale applications and microservice preparation.   |
| **🏢 Multi-tenancy Support**  | Built-in support for header, subdomain, and path-based tenant resolution strategies. Build SaaS applications with ease.                           |
| **💜 Laravel-Inspired DX**    | Familiar patterns and elegant syntax that Laravel developers will love. Powerful without the learning curve.                                      |
| **🛠️ CLI Tools**              | Comprehensive code generators for rapid development. Generate entities, commands, queries, controllers, and entire modules with a single command. |

---

## Quick Installation

```bash
# Install via Composer
composer require luminor/luminor

# Or create a new project
composer create-project luminor/luminor-skeleton my-app
```

For CLI tools globally:

```bash
composer global require luminor/luminor
```

---

## Quick Example

Here's a taste of what building with Luminor looks like:

### Define an Entity

```php
<?php

use Luminor\DDD\Domain\Abstractions\Entity;

final class Product extends Entity
{
    public function __construct(
        string $id,
        private string $name,
        private Money $price,
    ) {
        parent::__construct($id);
    }

    public static function create(string $name, Money $price): self
    {
        return new self(self::generateId(), $name, $price);
    }
}
```

### Create a Command

```php
<?php

use Luminor\DDD\Application\CQRS\Command;

final class CreateProductCommand implements Command
{
    public function __construct(
        public readonly string $name,
        public readonly int $priceInCents,
        public readonly string $currency = 'USD',
    ) {}
}
```

### Build an API Controller

```php
<?php

use Luminor\DDD\Infrastructure\Http\ApiController;

final class ProductController extends ApiController
{
    public function store(Request $request, Response $response): Response
    {
        $productId = $this->commandBus->dispatch(
            new CreateProductCommand(
                name: $request->getPayload()['name'],
                priceInCents: $request->getPayload()['price'],
            )
        );

        return $this->created($response, [
            'data' => ['id' => $productId],
        ]);
    }
}
```

---

## What's Included

| Feature               | Description                                                             |
| :-------------------- | :---------------------------------------------------------------------- |
| **Domain Layer**      | Entities, Aggregate Roots, Value Objects, Domain Events, Specifications |
| **Application Layer** | Commands, Queries, Handlers, DTOs, Validation                           |
| **HTTP Layer**        | Controllers, Routes, Middleware, Request/Response handling              |
| **Modules**           | Bounded contexts, Module service providers, Cross-module events         |
| **Authentication**    | Policies, Roles, Permissions, Password hashing, CSRF protection         |
| **Database**          | Migrations, Schema builder, Multi-database support                      |
| **Cache**             | Multiple drivers (Array, File), Remember pattern                        |
| **Queue**             | Jobs, Workers, Failed job handling, Multiple drivers                    |
| **Mail**              | Mailables, SMTP/Log/Array transports, Queue integration                 |
| **Session**           | Multiple drivers, Flash data                                            |
| **Storage**           | Filesystem abstraction, Streaming support                               |
| **Validation**        | 30+ built-in rules, Custom rules                                        |
| **Testing**           | In-memory buses, Domain assertions, Factories                           |

---

## Requirements

- PHP 8.2 or higher
- Composer 2.x

---

## Getting Help

- 📖 [Documentation](getting-started) - Comprehensive guides and API reference
- 💬 [GitHub Discussions](https://github.com/luminor-php/luminor/discussions) - Ask questions and share ideas
- 🐛 [Issue Tracker](https://github.com/luminor-php/luminor/issues) - Report bugs or request features
- 📝 [Contributing Guide](https://github.com/luminor-php/luminor/blob/main/CONTRIBUTING.md) - Help improve Luminor

---

## License

Luminor is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
