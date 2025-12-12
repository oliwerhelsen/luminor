# Lumina DDD Framework - Implementation Plan

## Project Overview

Building a commercial DDD boilerplate framework on top of Lumina PHP, similar to ABP.io for .NET. This framework will be sold as a licensed product for developers building REST APIs and domain-driven applications.

## Tech Stack

- **HTTP Layer**: utopia-php/http
- **PHP Version**: 8.2+
- **Architecture**: Domain-Driven Design with CQRS
- **Package Manager**: Composer

## Directory Structure

```
lumina-framework/
├── src/
│   ├── Domain/
│   │   ├── Abstractions/
│   │   ├── Repository/
│   │   └── Events/
│   ├── Application/
│   │   ├── Bus/
│   │   ├── CQRS/
│   │   ├── DTO/
│   │   └── Services/
│   ├── Infrastructure/
│   │   ├── Http/
│   │   ├── Persistence/
│   │   ├── EventBus/
│   │   └── Bus/
│   ├── Module/
│   ├── Multitenancy/
│   ├── Auth/
│   └── Kernel.php
├── contracts/
├── stubs/
├── bin/
├── config/
└── tests/
```

---

## Implementation Phases

### Phase 1: Project Foundation

**Goal**: Set up project structure and composer configuration

#### Tasks:

- [x] 1.1 Initialize composer.json with proper namespacing and dependencies
- [x] 1.2 Create directory structure
- [x] 1.3 Set up PHPUnit for testing
- [x] 1.4 Set up PHPStan for static analysis
- [x] 1.5 Create basic README.md with framework description

#### Files to create:

- `composer.json`
- `phpunit.xml`
- `phpstan.neon`
- `.gitignore`
- `README.md`

---

### Phase 2: Domain Layer Abstractions

**Goal**: Create base classes for DDD building blocks

#### Tasks:

- [x] 2.1 Create `Entity` base class with identity handling
- [x] 2.2 Create `AggregateRoot` extending Entity with event recording
- [x] 2.3 Create `ValueObject` abstract class with equality comparison
- [x] 2.4 Create `DomainEvent` base class with metadata
- [x] 2.5 Create `DomainException` for domain-specific errors
- [x] 2.6 Create `Specification` pattern for business rules
- [x] 2.7 Create `Enumeration` base class for type-safe enums

#### Files to create:

- `src/Domain/Abstractions/Entity.php`
- `src/Domain/Abstractions/AggregateRoot.php`
- `src/Domain/Abstractions/ValueObject.php`
- `src/Domain/Abstractions/DomainEvent.php`
- `src/Domain/Abstractions/DomainException.php`
- `src/Domain/Abstractions/Specification.php`
- `src/Domain/Abstractions/Enumeration.php`

#### Tests:

- `tests/Unit/Domain/EntityTest.php`
- `tests/Unit/Domain/AggregateRootTest.php`
- `tests/Unit/Domain/ValueObjectTest.php`
- `tests/Unit/Domain/SpecificationTest.php`

---

### Phase 3: Repository Contracts

**Goal**: Define repository interfaces and query abstractions

#### Tasks:

- [x] 3.1 Create `RepositoryInterface` with CRUD operations
- [x] 3.2 Create `ReadRepositoryInterface` for read-only access
- [x] 3.3 Create `Criteria` class for query building
- [x] 3.4 Create `Sorting` value object
- [x] 3.5 Create `Pagination` value object
- [x] 3.6 Create `Filter` specification classes

#### Files to create:

- `src/Domain/Repository/RepositoryInterface.php`
- `src/Domain/Repository/ReadRepositoryInterface.php`
- `src/Domain/Repository/Criteria.php`
- `src/Domain/Repository/Sorting.php`
- `src/Domain/Repository/Pagination.php`
- `src/Domain/Repository/Filter/Filter.php`
- `src/Domain/Repository/Filter/AndFilter.php`
- `src/Domain/Repository/Filter/OrFilter.php`
- `src/Domain/Repository/Filter/EqualsFilter.php`
- `src/Domain/Repository/Filter/ContainsFilter.php`

---

### Phase 4: Domain Events System

**Goal**: Implement event dispatching and handling

#### Tasks:

- [x] 4.1 Create `EventDispatcherInterface`
- [x] 4.2 Create `EventHandlerInterface`
- [x] 4.3 Create `EventStore` interface for event sourcing support
- [x] 4.4 Create `DomainEventPublisher` for aggregate events

#### Files to create:

- `src/Domain/Events/EventDispatcherInterface.php`
- `src/Domain/Events/EventHandlerInterface.php`
- `src/Domain/Events/EventStoreInterface.php`
- `src/Domain/Events/DomainEventPublisher.php`

---

### Phase 5: Application Layer - CQRS

**Goal**: Implement Command/Query separation

#### Tasks:

- [x] 5.1 Create `Command` marker interface
- [x] 5.2 Create `CommandHandler` interface
- [x] 5.3 Create `CommandBusInterface`
- [x] 5.4 Create `Query` marker interface
- [x] 5.5 Create `QueryHandler` interface
- [x] 5.6 Create `QueryBusInterface`
- [x] 5.7 Create `CommandValidator` for input validation

#### Files to create:

- `src/Application/CQRS/Command.php`
- `src/Application/CQRS/Query.php`
- `src/Application/Bus/CommandHandlerInterface.php`
- `src/Application/Bus/CommandBusInterface.php`
- `src/Application/Bus/QueryHandlerInterface.php`
- `src/Application/Bus/QueryBusInterface.php`
- `src/Application/Validation/CommandValidator.php`
- `src/Application/Validation/ValidationResult.php`

---

### Phase 6: Application Layer - DTOs & Services

**Goal**: Create data transfer and service abstractions

#### Tasks:

- [x] 6.1 Create `DataTransferObject` base class with mapping
- [x] 6.2 Create `PagedResult` for paginated responses
- [x] 6.3 Create `ApplicationService` base class
- [x] 6.4 Create `CrudApplicationService` with standard operations

#### Files to create:

- `src/Application/DTO/DataTransferObject.php`
- `src/Application/DTO/PagedResult.php`
- `src/Application/DTO/Mapper.php`
- `src/Application/Services/ApplicationService.php`
- `src/Application/Services/CrudApplicationService.php`

---

### Phase 7: Infrastructure - Bus Implementations

**Goal**: Concrete implementations of command/query buses

#### Tasks:

- [x] 7.1 Implement `SimpleCommandBus` with handler resolution
- [x] 7.2 Implement `SimpleQueryBus` with handler resolution
- [x] 7.3 Implement `SimpleEventDispatcher`
- [x] 7.4 Create `HandlerResolver` for dependency injection

#### Files to create:

- `src/Infrastructure/Bus/SimpleCommandBus.php`
- `src/Infrastructure/Bus/SimpleQueryBus.php`
- `src/Infrastructure/EventBus/SimpleEventDispatcher.php`
- `src/Infrastructure/Bus/HandlerResolver.php`

---

### Phase 8: Infrastructure - HTTP Layer

**Goal**: Lumina PHP integration with DDD patterns

#### Tasks:

- [x] 8.1 Create `ApiController` base class with common helpers
- [x] 8.2 Create `CrudController` for automatic CRUD endpoints
- [x] 8.3 Create `ApiResponse` standardized response format
- [x] 8.4 Create `ErrorResponse` with error codes
- [x] 8.5 Create `ValidationMiddleware` for request validation
- [x] 8.6 Create `AuthMiddleware` interface
- [x] 8.7 Create `CorsMiddleware`
- [x] 8.8 Create `ExceptionHandler` for global error handling
- [x] 8.9 Create route registration helpers

#### Files to create:

- `src/Infrastructure/Http/ApiController.php`
- `src/Infrastructure/Http/CrudController.php`
- `src/Infrastructure/Http/Response/ApiResponse.php`
- `src/Infrastructure/Http/Response/ErrorResponse.php`
- `src/Infrastructure/Http/Response/ValidationErrorResponse.php`
- `src/Infrastructure/Http/Middleware/MiddlewareInterface.php`
- `src/Infrastructure/Http/Middleware/ValidationMiddleware.php`
- `src/Infrastructure/Http/Middleware/AuthMiddlewareInterface.php`
- `src/Infrastructure/Http/Middleware/AbstractAuthMiddleware.php`
- `src/Infrastructure/Http/Middleware/CorsMiddleware.php`
- `src/Infrastructure/Http/ExceptionHandler.php`
- `src/Infrastructure/Http/RouteRegistrar.php`

---

### Phase 9: Infrastructure - Persistence

**Goal**: Database abstraction layer

#### Tasks:

- [x] 9.1 Create `UnitOfWork` interface
- [x] 9.2 Create `Transaction` wrapper
- [x] 9.3 Create `DoctrineRepository` base implementation
- [x] 9.4 Create `InMemoryRepository` for testing
- [x] 9.5 Create entity mapping helpers

#### Files to create:

- `src/Infrastructure/Persistence/UnitOfWorkInterface.php`
- `src/Infrastructure/Persistence/Transaction.php`
- `src/Infrastructure/Persistence/Doctrine/DoctrineRepository.php`
- `src/Infrastructure/Persistence/Doctrine/DoctrineUnitOfWork.php`
- `src/Infrastructure/Persistence/InMemory/InMemoryRepository.php`
- `src/Infrastructure/Persistence/EntityMapper.php`

---

### Phase 10: Module System

**Goal**: Modular architecture support

#### Tasks:

- [x] 10.1 Create `ModuleInterface` for module definition
- [x] 10.2 Create `ModuleLoader` for auto-discovery
- [x] 10.3 Create `ModuleDefinition` for configuration
- [x] 10.4 Create module lifecycle hooks

#### Files to create:

- `src/Module/ModuleInterface.php`
- `src/Module/ModuleLoader.php`
- `src/Module/ModuleDefinition.php`
- `src/Module/ModuleLifecycle.php`
- `src/Module/ModuleServiceProvider.php`

---

### Phase 11: Multitenancy Support

**Goal**: Built-in multi-tenant capabilities

#### Tasks:

- [x] 11.1 Create `TenantInterface`
- [x] 11.2 Create `TenantResolver` strategies (subdomain, header, path)
- [x] 11.3 Create `TenantAware` trait for entities
- [x] 11.4 Create `TenantMiddleware`
- [x] 11.5 Create tenant-scoped repository decorator

#### Files to create:

- `src/Multitenancy/TenantInterface.php`
- `src/Multitenancy/TenantContext.php`
- `src/Multitenancy/TenantResolver.php`
- `src/Multitenancy/TenantResolverInterface.php`
- `src/Multitenancy/TenantProviderInterface.php`
- `src/Multitenancy/TenantNotResolvedException.php`
- `src/Multitenancy/TenantAccessDeniedException.php`
- `src/Multitenancy/Strategy/SubdomainStrategy.php`
- `src/Multitenancy/Strategy/HeaderStrategy.php`
- `src/Multitenancy/Strategy/PathStrategy.php`
- `src/Multitenancy/TenantAware.php`
- `src/Multitenancy/TenantMiddleware.php`
- `src/Multitenancy/TenantScopedRepository.php`

#### Tests:

- `tests/Unit/Multitenancy/TenantContextTest.php`
- `tests/Unit/Multitenancy/TenantResolverTest.php`
- `tests/Unit/Multitenancy/TenantAwareTest.php`
- `tests/Unit/Multitenancy/TenantScopedRepositoryTest.php`
- `tests/Unit/Multitenancy/Strategy/HeaderStrategyTest.php`
- `tests/Unit/Multitenancy/Strategy/SubdomainStrategyTest.php`
- `tests/Unit/Multitenancy/Strategy/PathStrategyTest.php`

---

### Phase 12: Authentication & Authorization

**Goal**: Security abstractions

#### Tasks:

- [x] 12.1 Create `AuthenticatableInterface`
- [x] 12.2 Create `PermissionInterface`
- [x] 12.3 Create `RoleInterface`
- [x] 12.4 Create `PolicyInterface` for authorization
- [x] 12.5 Create `AuthorizationService`
- [x] 12.6 Create `CurrentUser` context

#### Files to create:

- `src/Auth/AuthenticatableInterface.php`
- `src/Auth/PermissionInterface.php`
- `src/Auth/RoleInterface.php`
- `src/Auth/PolicyInterface.php`
- `src/Auth/AbstractPolicy.php`
- `src/Auth/AuthorizationService.php`
- `src/Auth/AuthorizationException.php`
- `src/Auth/AuthenticationException.php`
- `src/Auth/CurrentUser.php`
- `src/Auth/HasPermissionsInterface.php`
- `src/Auth/HasRolesInterface.php`
- `src/Auth/Attributes/RequirePermission.php`
- `src/Auth/Attributes/RequireRole.php`
- `src/Auth/Attributes/RequireAuth.php`

#### Tests:

- `tests/Unit/Auth/CurrentUserTest.php`
- `tests/Unit/Auth/AuthorizationServiceTest.php`
- `tests/Unit/Auth/AttributesTest.php`

---

### Phase 13: Framework Kernel

**Goal**: Main entry point and bootstrapping

#### Tasks:

- [x] 13.1 Create `Kernel` class for application bootstrap
- [x] 13.2 Create `Container` wrapper for DI
- [x] 13.3 Create configuration loading
- [x] 13.4 Create service provider registration

#### Files to create:

- `src/Kernel.php`
- `src/Container/Container.php`
- `src/Container/ContainerException.php`
- `src/Container/NotFoundException.php`
- `src/Container/ServiceProviderInterface.php`
- `src/Container/AbstractServiceProvider.php`
- `src/Config/ConfigLoader.php`
- `src/Config/ConfigRepository.php`
- `config/framework.php`

#### Tests:

- `tests/Unit/Container/ContainerTest.php`
- `tests/Unit/Config/ConfigRepositoryTest.php`
- `tests/Unit/Kernel/KernelTest.php`

---

### Phase 14: CLI Tools

**Goal**: Code generation and development tools

#### Tasks:

- [x] 14.1 Create CLI application entry point
- [x] 14.2 Create `make:entity` command
- [x] 14.3 Create `make:repository` command
- [x] 14.4 Create `make:command` command (CQRS)
- [x] 14.5 Create `make:query` command
- [x] 14.6 Create `make:controller` command
- [x] 14.7 Create `make:module` command
- [x] 14.8 Create stub templates for all generators

#### Files to create:

- `bin/lumina`
- `src/Console/Application.php`
- `src/Console/Commands/MakeEntityCommand.php`
- `src/Console/Commands/MakeRepositoryCommand.php`
- `src/Console/Commands/MakeCommandCommand.php`
- `src/Console/Commands/MakeQueryCommand.php`
- `src/Console/Commands/MakeControllerCommand.php`
- `src/Console/Commands/MakeModuleCommand.php`
- `stubs/entity.stub`
- `stubs/aggregate-root.stub`
- `stubs/value-object.stub`
- `stubs/repository-interface.stub`
- `stubs/repository.stub`
- `stubs/command.stub`
- `stubs/command-handler.stub`
- `stubs/query.stub`
- `stubs/query-handler.stub`
- `stubs/controller.stub`
- `stubs/crud-controller.stub`
- `stubs/module.stub`

---

### Phase 15: Testing Utilities

**Goal**: Test helpers for framework users

#### Tasks:

- [x] 15.1 Create `TestCase` base class
- [x] 15.2 Create `InMemoryCommandBus` for testing
- [x] 15.3 Create `InMemoryQueryBus` for testing
- [x] 15.4 Create `InMemoryEventDispatcher` for testing
- [x] 15.5 Create assertion helpers
- [x] 15.6 Create factory helpers for entities

#### Files to create:

- `src/Testing/TestCase.php`
- `src/Testing/InMemoryCommandBus.php`
- `src/Testing/InMemoryQueryBus.php`
- `src/Testing/InMemoryEventDispatcher.php`
- `src/Testing/Assertions/DomainAssertions.php`
- `src/Testing/Factory/EntityFactory.php`

---

### Phase 16: Documentation & Examples

**Goal**: User documentation and sample code

#### Tasks:

- [x] 16.1 Write installation guide
- [x] 16.2 Write quick start guide
- [x] 16.3 Document domain layer usage
- [x] 16.4 Document application layer usage
- [x] 16.5 Document HTTP layer integration
- [x] 16.6 Create example module (e.g., UserModule)
- [x] 16.7 Create example application

#### Files to create:

- `docs/01-installation.md`
- `docs/02-quick-start.md`
- `docs/03-domain-layer.md`
- `docs/04-application-layer.md`
- `docs/05-http-layer.md`
- `docs/06-modules.md`
- `docs/07-multitenancy.md`
- `docs/08-testing.md`
- `examples/basic-api/`
- `examples/modular-app/`

---

## Code Style Guidelines

- Use PHP 8.2+ features (readonly, enums, constructor promotion)
- Follow PSR-12 coding standard
- Use strict types in all files
- Prefer composition over inheritance
- Use interfaces for all public contracts
- Keep classes final unless designed for extension
- Document all public methods with PHPDoc
- Write unit tests for all domain logic

## Naming Conventions

- Interfaces: `*Interface` suffix (e.g., `RepositoryInterface`)
- Abstract classes: `Abstract*` prefix or descriptive name
- Value Objects: Named after what they represent (e.g., `Email`, `Money`)
- Commands: Verb + Noun (e.g., `CreateUser`, `UpdateOrder`)
- Queries: `Get*` or `Find*` (e.g., `GetUserById`, `FindActiveOrders`)
- Events: Past tense (e.g., `UserCreated`, `OrderShipped`)

## Current Progress

- [x] Phase 1: Project Foundation
- [x] Phase 2: Domain Layer Abstractions
- [x] Phase 3: Repository Contracts
- [x] Phase 4: Domain Events System
- [x] Phase 5: Application Layer - CQRS
- [x] Phase 6: Application Layer - DTOs & Services
- [x] Phase 7: Infrastructure - Bus Implementations
- [x] Phase 8: Infrastructure - HTTP Layer
- [x] Phase 9: Infrastructure - Persistence
- [x] Phase 10: Module System
- [x] Phase 11: Multitenancy Support
- [x] Phase 12: Authentication & Authorization
- [x] Phase 13: Framework Kernel
- [x] Phase 14: CLI Tools
- [x] Phase 15: Testing Utilities
- [x] Phase 16: Documentation & Examples

**🎉 ALL PHASES COMPLETE! 🎉**

---

## Instructions for Claude Code

When working on this project:

1. **Follow the phases in order** - Each phase builds on the previous
2. **Create tests alongside implementation** - TDD approach preferred
3. **Use strict typing** - Add `declare(strict_types=1);` to all files
4. **Keep files focused** - One class per file, single responsibility
5. **Reference existing code** - Check implemented phases before adding new code
6. **Update progress** - Mark tasks as complete in this file

### Example prompt for Claude Code:

```
Implement Phase 2 - Domain Layer Abstractions. Start with Entity.php,
then AggregateRoot.php. Include unit tests. Follow the code style
guidelines in CLAUDE.md.
```

### To continue work:

```
Continue implementing the Lumina DDD Framework. Check CLAUDE.md for
current progress and implement the next incomplete phase.
```
