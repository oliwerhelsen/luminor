---
title: Core Concepts
layout: default
nav_order: 3
has_children: true
description: "DDD architecture, layers, and patterns in Luminor"
permalink: /core-concepts/
---

# Core Concepts

Understand the architectural patterns and design principles that power Luminor applications.

## Architecture Overview

Luminor follows a layered architecture based on Domain-Driven Design principles:

```
┌─────────────────────────────────────────────────────┐
│                   HTTP Layer                         │
│         Controllers, Routes, Middleware              │
├─────────────────────────────────────────────────────┤
│                Application Layer                     │
│     Commands, Queries, Handlers, DTOs, Services      │
├─────────────────────────────────────────────────────┤
│                  Domain Layer                        │
│   Entities, Value Objects, Aggregates, Events        │
├─────────────────────────────────────────────────────┤
│               Infrastructure Layer                   │
│      Repositories, External Services, Persistence    │
└─────────────────────────────────────────────────────┘
```

## The Three Layers

### Domain Layer

The heart of your application. Contains all business logic, rules, and domain concepts:

- **Entities** - Objects with unique identity
- **Value Objects** - Immutable objects defined by their attributes
- **Aggregate Roots** - Entry points to object clusters
- **Domain Events** - Capture important domain occurrences
- **Specifications** - Encapsulate business rules

[Learn more about the Domain Layer →](domain-layer)

### Application Layer

Orchestrates the domain to perform use cases using CQRS (Command Query Responsibility Segregation):

- **Commands** - Represent intentions to change state
- **Queries** - Retrieve data without side effects
- **Handlers** - Execute commands and queries
- **DTOs** - Transfer data between layers

[Learn more about the Application Layer →](application-layer)

### HTTP Layer

Exposes your application to the outside world:

- **Controllers** - Handle HTTP requests
- **Routes** - Map URLs to controllers
- **Middleware** - Process requests/responses

[Learn more about the HTTP Layer →](http-layer)

## CQRS Pattern

Luminor implements Command Query Responsibility Segregation to separate read and write operations:

```php
// Write operation (Command)
$this->commandBus->dispatch(new CreateProductCommand(
    name: 'Widget',
    price: 2999,
));

// Read operation (Query)
$product = $this->queryBus->dispatch(new GetProductQuery($id));
```

This separation provides:

- **Clarity** - Clear distinction between reads and writes
- **Scalability** - Optimize read and write paths independently
- **Testability** - Easy to test commands and queries in isolation

## Key Principles

1. **Dependency Rule** - Dependencies point inward. The domain layer has no dependencies on outer layers.

2. **Rich Domain Model** - Business logic lives in domain objects, not in services.

3. **Ubiquitous Language** - Use domain terminology consistently throughout the codebase.

4. **Bounded Contexts** - Organize large applications into modules with clear boundaries.
