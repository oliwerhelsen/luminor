# Changelog

All notable changes to the Luminor DDD Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-13

### 🎉 Initial Release

The first stable release of Luminor - a modern, open-source Domain-Driven Design (DDD) framework for PHP!

### Added

#### Core DDD Features
- **Domain Layer** - Entities, Aggregate Roots, Value Objects, Domain Events, Specifications, and Enumerations
- **Application Layer** - Complete CQRS implementation with Commands, Queries, and Handlers
- **Repository Pattern** - Clean data access with filtering, sorting, and pagination support
- **Event System** - Domain event publishing and handling with event dispatcher

#### Infrastructure
- **HTTP Layer** - ApiController, CrudController base classes with standardized responses
- **Middleware** - Authentication, Authorization, CORS, CSRF, Validation, Rate Limiting, and Tenant middleware
- **Database** - Schema builder with migrations support for MySQL, PostgreSQL, and SQLite
- **Cache** - Multiple drivers (Array for testing, File for persistence)
- **Queue** - Background job processing with Sync, Database, Redis, and Valkey drivers
- **Session** - Session management with Array, File, and Database drivers
- **Storage** - Filesystem abstraction for file operations
- **Mail** - Email sending with SMTP, Log, and Array transports
- **Logging** - PSR-3 compatible logging with File, Stdout, Array, and Null drivers

#### Authentication & Authorization
- **JWT Authentication** - Token-based authentication with customizable expiration
- **API Token Management** - Generate and manage API tokens
- **Session Authentication** - Traditional session-based auth
- **OpenID Connect** - Enterprise SSO integration
- **Multi-Factor Authentication** - TOTP-based MFA support
- **Authorization** - Policy-based access control with roles and permissions
- **Security Features** - Password hashing (Bcrypt, Argon2), CSRF protection, rate limiting

#### Multi-tenancy
- **Tenant Resolution Strategies** - Subdomain, Header, and Path-based tenant identification
- **Tenant Context** - Automatic tenant context management
- **Tenant-Scoped Repositories** - Data isolation at the repository level

#### Modules System
- **Modular Architecture** - Organize applications into bounded contexts
- **Module Lifecycle** - Register, boot, and manage modules
- **Service Providers** - Dependency injection and service registration per module

#### Developer Experience
- **CLI Tools** - 24+ console commands for code generation and management
  - Entity, Command, Query, Handler, Repository, Controller generators
  - Migration commands (migrate, rollback, reset, fresh, status)
  - Queue worker commands (work, failed, retry, flush)
  - Development server command
- **Validation** - 30+ built-in validation rules with custom rule support
- **Testing Utilities** - In-memory buses, domain assertions, and test helpers
- **Configuration Management** - Environment-based configuration with YAML/PHP support
- **Dependency Injection** - PSR-11 compatible container with auto-wiring

#### Documentation & Examples
- **Comprehensive Documentation** - 27 documentation files covering all features
- **Tutorials** - Step-by-step guides for building Todo and Product APIs
- **Authentication Tutorials** - 7 detailed authentication implementation guides
- **Working Examples** - Complete example applications (basic-api, modular-app)

#### Quality Assurance
- **PHPUnit Tests** - Comprehensive test coverage with Unit and Integration test suites
- **PHPStan Level 8** - Maximum static analysis strictness
- **Strict Types** - 100% of source files use `declare(strict_types=1)`
- **CI/CD** - GitHub Actions workflows for automated testing and documentation deployment
- **PSR-12 Compliant** - Follows PHP-FIG coding standards

### Requirements
- PHP 8.2 or higher
- Composer 2.x

### Optional Dependencies
- `vlucas/phpdotenv` ^5.5 - For .env file support
- `doctrine/dbal` ^3.0|^4.0 - For database functionality
- `doctrine/migrations` ^3.0 - For database migrations
- `predis/predis` ^2.0 - For Redis cache/queue support
- `monolog/monolog` ^3.0 - For advanced logging features

---

## Upgrade Guide

As this is the initial release, there are no upgrade instructions. For future releases, upgrade guides will be provided here.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for details on contributing to Luminor.

## Security

See [SECURITY.md](SECURITY.md) for our security policy and how to report vulnerabilities.

## License

Luminor is open-sourced software licensed under the [MIT License](LICENSE).

---

[1.0.0]: https://github.com/luminor-php/luminor/releases/tag/v1.0.0
