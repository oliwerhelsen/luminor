---
title: Quick Start
layout: default
parent: Getting Started
nav_order: 2
description: "Create your first CRUD API with Luminor"
---

# Quick Start Guide

This guide will help you create a simple CRUD API using the Luminor DDD Framework.

## Creating Your First Entity

Use the CLI to generate an entity:

```bash
./vendor/bin/luminor make:entity User
```

This creates `src/Domain/Entities/User.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use Luminor\Domain\Abstractions\Entity;

final class User extends Entity
{
    public function __construct(
        string $id,
        private string $name,
        private string $email,
    ) {
        parent::__construct($id);
    }

    public static function create(string $name, string $email): self
    {
        return new self(self::generateId(), $name, $email);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
    }
}
```

## Creating a Repository

Generate a repository interface and implementation:

```bash
./vendor/bin/luminor make:repository User --implementation
```

## Creating Commands and Queries

Generate CQRS components:

```bash
# Create a command with handler
./vendor/bin/luminor make:command CreateUser

# Create a query with handler
./vendor/bin/luminor make:query GetUserById
```

Example command:

```php
<?php

declare(strict_types=1);

namespace App\Application\Commands;

use Luminor\Application\CQRS\Command;

final class CreateUserCommand implements Command
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {
    }
}
```

Example handler:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Commands\CreateUserCommand;
use App\Domain\Entities\User;
use App\Domain\Repository\UserRepositoryInterface;
use Luminor\Application\Bus\CommandHandlerInterface;

final class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(CreateUserCommand $command): string
    {
        $user = User::create($command->name, $command->email);
        $this->userRepository->save($user);

        return $user->getId();
    }
}
```

## Creating a Controller

Generate an API controller:

```bash
./vendor/bin/luminor make:controller User
```

Example controller usage:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Commands\CreateUserCommand;
use Luminor\Application\Bus\CommandBusInterface;
use Luminor\Infrastructure\Http\ApiController;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class UserController extends ApiController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getPayload();

        $userId = $this->commandBus->dispatch(new CreateUserCommand(
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
        ));

        return $this->created($response, [
            'data' => ['id' => $userId],
            'message' => 'User created successfully',
        ]);
    }
}
```

## Registering Routes

In your `public/index.php` or a routes file:

```php
<?php

use Luminor\Http\HttpKernel;

$http = Http::getInstance();

$http->post('/users')
    ->inject('request')
    ->inject('response')
    ->inject('userController')
    ->action(function ($request, $response, $controller) {
        return $controller->store($request, $response);
    });
```

## Testing Your API

Create a test:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\Commands\CreateUserCommand;
use App\Application\Handlers\CreateUserCommandHandler;
use PHPUnit\Framework\TestCase;
use Luminor\Testing\InMemoryRepository;

final class CreateUserCommandHandlerTest extends TestCase
{
    public function testCreatesUser(): void
    {
        $repository = new InMemoryUserRepository();
        $handler = new CreateUserCommandHandler($repository);

        $command = new CreateUserCommand(
            name: 'John Doe',
            email: 'john@example.com',
        );

        $userId = $handler($command);

        $this->assertNotEmpty($userId);
        $this->assertNotNull($repository->findById($userId));
    }
}
```

## Next Steps

- Learn about [Domain Layer](03-domain-layer.md) patterns
- Explore [Application Layer](04-application-layer.md) for CQRS
- Set up [Modules](06-modules.md) for larger applications
