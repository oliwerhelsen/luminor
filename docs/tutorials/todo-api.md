---
title: Building a Todo API
layout: default
parent: Tutorials
nav_order: 2
description: "Beginner-friendly guide to building a simple Todo API with Luminor"
---

# Building a Todo API

{: .no_toc }

A beginner-friendly tutorial to get you started with Luminor. Build a simple task management API while learning the fundamentals of Domain-Driven Design.

**Difficulty:** Beginner | **Time:** 20-30 minutes
{: .fs-5 .fw-300 }

## Table of Contents

{: .no_toc .text-delta }

1. TOC
   {:toc}

---

## What You'll Build

A simple Todo API with these endpoints:

| Method | Endpoint              | Description             |
| :----- | :-------------------- | :---------------------- |
| GET    | `/todos`              | List all todos          |
| GET    | `/todos/:id`          | Get a single todo       |
| POST   | `/todos`              | Create a new todo       |
| PATCH  | `/todos/:id/complete` | Mark a todo as complete |
| DELETE | `/todos/:id`          | Delete a todo           |

## Prerequisites

- Luminor installed (see [Installation](../installation))
- Basic PHP knowledge

## Project Structure

```
src/
├── Domain/
│   ├── Entities/
│   │   └── Todo.php
│   └── Repository/
│       └── TodoRepositoryInterface.php
├── Application/
│   ├── Commands/
│   │   ├── CreateTodoCommand.php
│   │   ├── CompleteTodoCommand.php
│   │   └── DeleteTodoCommand.php
│   ├── Queries/
│   │   ├── GetTodoQuery.php
│   │   └── ListTodosQuery.php
│   └── Handlers/
│       └── ... (one for each command/query)
└── Infrastructure/
    ├── Http/Controllers/
    │   └── TodoController.php
    └── Persistence/
        └── InMemoryTodoRepository.php
```

---

## Step 1: Create the Todo Entity

An entity is an object with a unique identity. Our Todo entity represents a task that can be created, completed, and deleted.

Create `src/Domain/Entities/Todo.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use Luminor\Domain\Abstractions\Entity;

final class Todo extends Entity
{
    public function __construct(
        string $id,
        private string $title,
        private bool $completed,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $completedAt,
    ) {
        parent::__construct($id);
    }

    /**
     * Factory method to create a new todo.
     */
    public static function create(string $title): self
    {
        return new self(
            id: self::generateId(),
            title: $title,
            completed: false,
            createdAt: new \DateTimeImmutable(),
            completedAt: null,
        );
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * Mark this todo as complete.
     */
    public function complete(): void
    {
        if ($this->completed) {
            return; // Already completed
        }

        $this->completed = true;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Update the todo title.
     */
    public function updateTitle(string $title): void
    {
        $this->title = $title;
    }
}
```

{: .note }

> The entity contains **behavior** (`complete()`, `updateTitle()`), not just data. This is a key principle of DDD—business logic lives in domain objects.

---

## Step 2: Define the Repository Interface

The repository provides an abstraction for data storage. We define it as an interface so we can swap implementations (in-memory for testing, database for production).

Create `src/Domain/Repository/TodoRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entities\Todo;

interface TodoRepositoryInterface
{
    public function findById(string $id): ?Todo;

    /**
     * @return Todo[]
     */
    public function findAll(): array;

    public function save(Todo $todo): void;

    public function delete(Todo $todo): void;
}
```

---

## Step 3: Create Commands

Commands represent intentions to change the system. Each command is a simple data class.

### CreateTodoCommand

Create `src/Application/Commands/CreateTodoCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Commands;

use Luminor\Application\CQRS\Command;

final class CreateTodoCommand implements Command
{
    public function __construct(
        public readonly string $title,
    ) {
    }
}
```

### CompleteTodoCommand

Create `src/Application/Commands/CompleteTodoCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Commands;

use Luminor\Application\CQRS\Command;

final class CompleteTodoCommand implements Command
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
```

### DeleteTodoCommand

Create `src/Application/Commands/DeleteTodoCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Commands;

use Luminor\Application\CQRS\Command;

final class DeleteTodoCommand implements Command
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
```

---

## Step 4: Create Queries

Queries retrieve data without changing the system state.

### GetTodoQuery

Create `src/Application/Queries/GetTodoQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Luminor\Application\CQRS\Query;

final class GetTodoQuery implements Query
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
```

### ListTodosQuery

Create `src/Application/Queries/ListTodosQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Luminor\Application\CQRS\Query;

final class ListTodosQuery implements Query
{
    public function __construct(
        public readonly ?bool $completed = null, // Optional filter
    ) {
    }
}
```

---

## Step 5: Create Handlers

Handlers contain the logic for processing commands and queries.

### CreateTodoCommandHandler

Create `src/Application/Handlers/CreateTodoCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Commands\CreateTodoCommand;
use App\Domain\Entities\Todo;
use App\Domain\Repository\TodoRepositoryInterface;
use Luminor\Application\Bus\CommandHandlerInterface;

final class CreateTodoCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly TodoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(CreateTodoCommand $command): string
    {
        $todo = Todo::create($command->title);

        $this->repository->save($todo);

        return $todo->getId();
    }
}
```

### CompleteTodoCommandHandler

Create `src/Application/Handlers/CompleteTodoCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Commands\CompleteTodoCommand;
use App\Domain\Repository\TodoRepositoryInterface;
use Luminor\Application\Bus\CommandHandlerInterface;

final class CompleteTodoCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly TodoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(CompleteTodoCommand $command): bool
    {
        $todo = $this->repository->findById($command->id);

        if ($todo === null) {
            return false;
        }

        $todo->complete();
        $this->repository->save($todo);

        return true;
    }
}
```

### DeleteTodoCommandHandler

Create `src/Application/Handlers/DeleteTodoCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Commands\DeleteTodoCommand;
use App\Domain\Repository\TodoRepositoryInterface;
use Luminor\Application\Bus\CommandHandlerInterface;

final class DeleteTodoCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly TodoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(DeleteTodoCommand $command): bool
    {
        $todo = $this->repository->findById($command->id);

        if ($todo === null) {
            return false;
        }

        $this->repository->delete($todo);

        return true;
    }
}
```

### GetTodoQueryHandler

Create `src/Application/Handlers/GetTodoQueryHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Queries\GetTodoQuery;
use App\Domain\Repository\TodoRepositoryInterface;
use Luminor\Application\Bus\QueryHandlerInterface;

final class GetTodoQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly TodoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetTodoQuery $query): ?array
    {
        $todo = $this->repository->findById($query->id);

        if ($todo === null) {
            return null;
        }

        return $this->toArray($todo);
    }

    private function toArray($todo): array
    {
        return [
            'id' => $todo->getId(),
            'title' => $todo->getTitle(),
            'completed' => $todo->isCompleted(),
            'created_at' => $todo->getCreatedAt()->format('c'),
            'completed_at' => $todo->getCompletedAt()?->format('c'),
        ];
    }
}
```

### ListTodosQueryHandler

Create `src/Application/Handlers/ListTodosQueryHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Queries\ListTodosQuery;
use App\Domain\Repository\TodoRepositoryInterface;
use Luminor\Application\Bus\QueryHandlerInterface;

final class ListTodosQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly TodoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(ListTodosQuery $query): array
    {
        $todos = $this->repository->findAll();

        // Filter by completion status if specified
        if ($query->completed !== null) {
            $todos = array_filter(
                $todos,
                fn($todo) => $todo->isCompleted() === $query->completed
            );
        }

        return array_map(fn($todo) => [
            'id' => $todo->getId(),
            'title' => $todo->getTitle(),
            'completed' => $todo->isCompleted(),
            'created_at' => $todo->getCreatedAt()->format('c'),
            'completed_at' => $todo->getCompletedAt()?->format('c'),
        ], array_values($todos));
    }
}
```

---

## Step 6: Implement the Repository

Create a simple in-memory implementation for development and testing.

Create `src/Infrastructure/Persistence/InMemoryTodoRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Todo;
use App\Domain\Repository\TodoRepositoryInterface;

final class InMemoryTodoRepository implements TodoRepositoryInterface
{
    /** @var array<string, Todo> */
    private array $todos = [];

    public function findById(string $id): ?Todo
    {
        return $this->todos[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->todos);
    }

    public function save(Todo $todo): void
    {
        $this->todos[$todo->getId()] = $todo;
    }

    public function delete(Todo $todo): void
    {
        unset($this->todos[$todo->getId()]);
    }
}
```

---

## Step 7: Create the Controller

Create `src/Infrastructure/Http/Controllers/TodoController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Commands\CreateTodoCommand;
use App\Application\Commands\CompleteTodoCommand;
use App\Application\Commands\DeleteTodoCommand;
use App\Application\Queries\GetTodoQuery;
use App\Application\Queries\ListTodosQuery;
use Luminor\Application\Bus\CommandBusInterface;
use Luminor\Application\Bus\QueryBusInterface;
use Luminor\Infrastructure\Http\ApiController;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class TodoController extends ApiController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    /**
     * GET /todos
     */
    public function index(Request $request, Response $response): Response
    {
        $completed = $request->getQuery('completed');

        $todos = $this->queryBus->dispatch(new ListTodosQuery(
            completed: $completed !== null ? $completed === 'true' : null,
        ));

        return $this->success($response, ['data' => $todos]);
    }

    /**
     * GET /todos/:id
     */
    public function show(Request $request, Response $response, string $id): Response
    {
        $todo = $this->queryBus->dispatch(new GetTodoQuery($id));

        if ($todo === null) {
            return $this->notFound($response, 'Todo not found');
        }

        return $this->success($response, ['data' => $todo]);
    }

    /**
     * POST /todos
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getPayload();

        if (empty($data['title'])) {
            return $this->validationError($response, [
                'title' => ['Title is required'],
            ]);
        }

        $todoId = $this->commandBus->dispatch(new CreateTodoCommand(
            title: $data['title'],
        ));

        return $this->created($response, [
            'data' => ['id' => $todoId],
            'message' => 'Todo created successfully',
        ]);
    }

    /**
     * PATCH /todos/:id/complete
     */
    public function complete(Request $request, Response $response, string $id): Response
    {
        $success = $this->commandBus->dispatch(new CompleteTodoCommand($id));

        if (!$success) {
            return $this->notFound($response, 'Todo not found');
        }

        return $this->success($response, ['message' => 'Todo marked as complete']);
    }

    /**
     * DELETE /todos/:id
     */
    public function destroy(Request $request, Response $response, string $id): Response
    {
        $success = $this->commandBus->dispatch(new DeleteTodoCommand($id));

        if (!$success) {
            return $this->notFound($response, 'Todo not found');
        }

        return $this->noContent($response);
    }
}
```

---

## Step 8: Set Up Routes

Create `public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Commands\CreateTodoCommand;
use App\Application\Commands\CompleteTodoCommand;
use App\Application\Commands\DeleteTodoCommand;
use App\Application\Handlers\CreateTodoCommandHandler;
use App\Application\Handlers\CompleteTodoCommandHandler;
use App\Application\Handlers\DeleteTodoCommandHandler;
use App\Application\Handlers\GetTodoQueryHandler;
use App\Application\Handlers\ListTodosQueryHandler;
use App\Application\Queries\GetTodoQuery;
use App\Application\Queries\ListTodosQuery;
use App\Infrastructure\Http\Controllers\TodoController;
use App\Infrastructure\Persistence\InMemoryTodoRepository;
use Luminor\Infrastructure\Bus\SimpleCommandBus;
use Luminor\Infrastructure\Bus\SimpleQueryBus;
use Luminor\Http\HttpKernel;

// Create HTTP instance
$http = Http::getInstance();

// Set up repository
$todoRepository = new InMemoryTodoRepository();

// Set up command bus
$commandBus = new SimpleCommandBus();
$commandBus->registerHandler(CreateTodoCommand::class, new CreateTodoCommandHandler($todoRepository));
$commandBus->registerHandler(CompleteTodoCommand::class, new CompleteTodoCommandHandler($todoRepository));
$commandBus->registerHandler(DeleteTodoCommand::class, new DeleteTodoCommandHandler($todoRepository));

// Set up query bus
$queryBus = new SimpleQueryBus();
$queryBus->registerHandler(GetTodoQuery::class, new GetTodoQueryHandler($todoRepository));
$queryBus->registerHandler(ListTodosQuery::class, new ListTodosQueryHandler($todoRepository));

// Create controller
$todoController = new TodoController($commandBus, $queryBus);

// Define routes
$http->get('/todos')
    ->inject('request')
    ->inject('response')
    ->action(fn($request, $response) => $todoController->index($request, $response));

$http->get('/todos/:id')
    ->param('id', '', 'string', 'Todo ID')
    ->inject('request')
    ->inject('response')
    ->action(fn($id, $request, $response) => $todoController->show($request, $response, $id));

$http->post('/todos')
    ->inject('request')
    ->inject('response')
    ->action(fn($request, $response) => $todoController->store($request, $response));

$http->patch('/todos/:id/complete')
    ->param('id', '', 'string', 'Todo ID')
    ->inject('request')
    ->inject('response')
    ->action(fn($id, $request, $response) => $todoController->complete($request, $response, $id));

$http->delete('/todos/:id')
    ->param('id', '', 'string', 'Todo ID')
    ->inject('request')
    ->inject('response')
    ->action(fn($id, $request, $response) => $todoController->destroy($request, $response, $id));

// Run the application
$http->run();
```

---

## Step 9: Test Your API

Start the development server:

```bash
vendor/bin/luminor serve
```

### Create a Todo

```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title": "Learn Luminor"}'
```

Response:

```json
{
  "data": { "id": "abc123..." },
  "message": "Todo created successfully"
}
```

### List All Todos

```bash
curl http://localhost:8080/todos
```

### List Only Incomplete Todos

```bash
curl "http://localhost:8080/todos?completed=false"
```

### Get a Single Todo

```bash
curl http://localhost:8080/todos/abc123...
```

### Mark a Todo as Complete

```bash
curl -X PATCH http://localhost:8080/todos/abc123.../complete
```

### Delete a Todo

```bash
curl -X DELETE http://localhost:8080/todos/abc123...
```

---

## Summary

You've built a complete Todo API using Luminor! Here's what you learned:

✅ **Entities** - Objects with identity and behavior  
✅ **Repository Pattern** - Abstract data storage  
✅ **Commands** - Represent intentions to change state  
✅ **Queries** - Retrieve data without side effects  
✅ **Handlers** - Process commands and queries  
✅ **Controllers** - Handle HTTP requests

## Key Concepts Recap

| Concept        | Purpose                     | Example                    |
| :------------- | :-------------------------- | :------------------------- |
| **Entity**     | Object with unique identity | `Todo`                     |
| **Repository** | Abstract data persistence   | `TodoRepositoryInterface`  |
| **Command**    | Change system state         | `CreateTodoCommand`        |
| **Query**      | Read system state           | `ListTodosQuery`           |
| **Handler**    | Execute command/query logic | `CreateTodoCommandHandler` |
| **Controller** | Handle HTTP requests        | `TodoController`           |

## Next Steps

Ready for more? Try these challenges:

1. **Add an `uncomplete` endpoint** - Allow marking todos as incomplete
2. **Add due dates** - Extend the entity with a due date
3. **Add persistence** - Swap InMemoryTodoRepository for a database implementation
4. **Add validation** - Use Luminor's validation system

Then check out the more advanced [Product API Tutorial](product-api) to learn about Value Objects and more complex domain modeling.

## Related Documentation

- [Domain Layer](../domain-layer) - Deep dive into entities, value objects, and aggregates
- [Application Layer](../application-layer) - More about CQRS and handlers
- [Testing](../testing) - Write tests for your handlers
