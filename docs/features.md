---
title: Features
layout: default
nav_order: 4
has_children: true
description: "Comprehensive feature documentation for Luminor"
permalink: /features/
---

# Features

Luminor comes packed with features to help you build robust, scalable applications.

## Modular Architecture

Organize your application into self-contained modules with bounded contexts. Perfect for large applications and microservice preparation.

- [Modules](modules) - Create and manage application modules
- [Multitenancy](multitenancy) - Build SaaS applications with tenant isolation

## Infrastructure

Essential services for production applications:

- [Database & Migrations](database) - Schema builder and version control
- [Cache](cache) - Improve performance with caching
- [Queue](queues) - Background job processing
- [Session](session) - User session management
- [Storage](storage) - File system abstraction
- [Mail](mail) - Send emails with multiple transports
- [Logging](logging) - PSR-3 compatible logging

## Security

Protect your application from common vulnerabilities:

- [Security](security) - Password hashing, CSRF protection, and more

## Developer Experience

Tools to boost your productivity:

- [Console Commands](console) - CLI tools and code generators
- [Validation](validation) - 30+ built-in validation rules
- [Testing](testing) - Testing utilities and assertions

## Feature Matrix

| Feature          | Description                  | Drivers/Options           |
| :--------------- | :--------------------------- | :------------------------ |
| **Modules**      | Bounded context organization | Auto-loading, Manual      |
| **Multitenancy** | Tenant isolation             | Header, Subdomain, Path   |
| **Database**     | Schema & migrations          | MySQL, PostgreSQL, SQLite |
| **Cache**        | Data caching                 | Array, File               |
| **Queue**        | Background jobs              | Sync, Database, Redis     |
| **Session**      | State management             | Array, File, Database     |
| **Storage**      | File operations              | Local                     |
| **Mail**         | Email sending                | SMTP, Log, Array          |
| **Logging**      | Application logs             | File, Stdout, Stack       |

## Quick Links

{: .highlight }
Looking to get started quickly? Check out our [Tutorials](tutorials) section for step-by-step guides on building complete applications.
