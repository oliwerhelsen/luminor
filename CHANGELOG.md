# Changelog

All notable changes to the Luminor DDD Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-12-13

### 🎉 Major Release - Event Sourcing, OpenAPI & Observability

The v2.0 release brings three major feature additions to Luminor, significantly enhancing its capabilities for building production-ready, event-driven applications.

### Added

#### Event Sourcing
- **Event Store** - Database and in-memory implementations for persisting domain events
  - `DatabaseEventStore` - Production-ready event store with MySQL, PostgreSQL, and SQLite support
  - `InMemoryEventStore` - Fast in-memory storage for testing and development
  - Event versioning and metadata support
  - Temporal queries (events by date range, type, aggregate)
  - Complete audit trail of all domain changes
- **Event-Sourced Aggregates** - `EventSourcedAggregateRoot` base class for event-sourced entities
  - Automatic event application via convention-based apply methods
  - Aggregate rehydration from event streams
  - Version tracking
  - Event replay capabilities
- **Snapshots** - Performance optimization for aggregates with many events
  - `DatabaseSnapshotStore` and `InMemorySnapshotStore` implementations
  - Configurable snapshot thresholds
  - Automatic snapshot creation and retrieval
  - Reduces event replay overhead
- **Projections** - Read model generation from event streams
  - `ProjectorInterface` and `AbstractProjector` base class
  - `ProjectionManager` for coordinating multiple projectors
  - Convention-based event handling (`when{EventName}` methods)
  - Rebuild projections from event store
- **Event Store CLI Commands**
  - `events:list` - List and filter events from the store
  - `events:stats` - Display event store statistics
  - `projection:rebuild` - Rebuild read model projections
- **Event Repository** - `EventSourcedRepository` for loading/saving event-sourced aggregates
- **Migrations** - Database schema for `domain_events` and `snapshots` tables
- **Configuration** - `config/events.php` for event store and projection settings

#### OpenAPI Documentation
- **OpenAPI Generator** - Automatic API documentation generation
  - OpenAPI 3.0 specification support
  - JSON and YAML output formats
  - Configurable servers and security schemes
  - Schema definitions with reusable components
- **PHP Attributes** - Document APIs using modern PHP 8 attributes
  - `#[OpenApiOperation]` - Document endpoint operations
  - `#[OpenApiParameter]` - Define query, path, and header parameters
  - `#[OpenApiRequestBody]` - Document request payloads
  - `#[OpenApiResponse]` - Define response schemas and examples
- **CLI Command**
  - `openapi:generate` - Generate OpenAPI specification from code
  - Support for JSON and YAML output formats
  - Integration-ready for Swagger UI and other tools
- **Documentation** - Comprehensive guide for API documentation

#### Observability & Metrics
- **Metrics Collection** - Track application performance and health
  - `MetricsInterface` with multiple implementations
  - `InMemoryMetrics` - Development and testing
  - `NullMetrics` - Disabled metrics for minimal overhead
- **Metric Types**
  - **Counters** - Track cumulative values (requests, errors, items processed)
  - **Gauges** - Track point-in-time values (memory usage, queue depth)
  - **Histograms** - Track value distributions (response sizes, order values)
  - **Timers** - Measure operation duration with automatic timing
- **Tagging** - Add dimensions to metrics for detailed analysis
- **Performance Monitoring**
  - Slow query detection
  - Request tracking
  - Command execution monitoring
- **CLI Command**
  - `metrics:show` - Display collected metrics and statistics
  - Percentile calculations (P50, P95, P99) for histograms
- **Configuration** - `config/observability.php` for metrics settings
- **Production Integration** - Examples for StatsD, Prometheus, and CloudWatch

#### Enhanced Domain Events
- **Metadata Support** - Added metadata field to `DomainEvent` base class
- **Payload Method** - Made `getPayload()` public for event serialization
- **Event Methods** - Added `withMetadata()` for event enrichment

#### Documentation
- **Event Sourcing Guide** - Complete guide with examples and best practices
- **OpenAPI Documentation** - Step-by-step guide for API documentation
- **Observability Guide** - Metrics collection and monitoring guide
- **Best Practices** - Updated with event sourcing patterns

### Changed

- **DomainEvent** - Constructor now accepts optional metadata parameter
- **Documentation Structure** - Added new navigation entries for v2 features

### Technical Details

#### Event Sourcing Architecture
```
Event Store -> Events -> Aggregate Rehydration
            ↓
       Snapshots (optional)
            ↓
       Projections -> Read Models
```

#### New Service Providers
- `EventStoreServiceProvider` - Registers event store implementations
- `ObservabilityServiceProvider` - Registers metrics collection

#### Database Tables
- `domain_events` - Stores all domain events with full payload
- `snapshots` - Caches aggregate state at specific versions

### Migration Guide from v1.x

#### For Existing Applications

1. **Run Migrations**
   ```bash
   php luminor migrate
   ```

2. **Update Configuration**
   - Add `config/events.php` for event sourcing
   - Add `config/observability.php` for metrics

3. **Optional: Convert to Event Sourcing**
   - Change aggregates from `AggregateRoot` to `EventSourcedAggregateRoot`
   - Implement apply methods for events
   - Update repositories to extend `EventSourcedRepository`

4. **Add API Documentation** (Optional)
   - Annotate controllers with OpenAPI attributes
   - Generate documentation: `php luminor openapi:generate`

5. **Add Metrics** (Optional)
   - Inject `MetricsInterface` into services
   - Track key performance indicators

#### Breaking Changes

**None** - v2.0 is fully backward compatible. All new features are opt-in.

### Performance Improvements

- **Snapshots** - Reduce aggregate loading time by up to 90% for event-sourced aggregates
- **In-Memory Event Store** - Optimized for testing with minimal overhead
- **Metrics** - Low-overhead metric collection with configurable drivers

### Requirements

- PHP 8.2 or higher (unchanged)
- Composer 2.x (unchanged)

### Optional Dependencies

No new required dependencies. Event sourcing and observability features work with existing dependencies.

---

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
