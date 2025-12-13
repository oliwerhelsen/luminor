---
title: Building a Product API
layout: default
parent: Tutorials
nav_order: 1
description: "Step-by-step guide to building a REST API with Luminor using DDD patterns"
---

# Building a Product API

{: .no_toc }

Learn how to build a complete REST API for managing products using Luminor's DDD architecture, including entities, value objects, CQRS, and the repository pattern.

**Difficulty:** Intermediate | **Time:** 30-45 minutes
{: .fs-5 .fw-300 }

## Table of Contents

{: .no_toc .text-delta }

1. TOC
   {:toc}

---

## What You'll Build

A complete REST API with the following endpoints:

| Method | Endpoint        | Description                       |
| :----- | :-------------- | :-------------------------------- |
| GET    | `/products`     | List all products with pagination |
| GET    | `/products/:id` | Get a single product              |
| POST   | `/products`     | Create a new product              |
| PUT    | `/products/:id` | Update an existing product        |
| DELETE | `/products/:id` | Delete a product                  |

## Prerequisites

- Luminor installed (see [Installation](../installation))
- Basic understanding of PHP 8.2+
- Familiarity with REST APIs

## Project Structure

By the end of this tutorial, you'll have the following structure:

```
src/
├── Domain/
│   ├── Entities/
│   │   └── Product.php
│   ├── ValueObjects/
│   │   └── Money.php
│   └── Repository/
│       └── ProductRepositoryInterface.php
├── Application/
│   ├── Commands/
│   │   ├── CreateProductCommand.php
│   │   └── UpdateProductCommand.php
│   ├── Queries/
│   │   ├── GetProductQuery.php
│   │   └── ListProductsQuery.php
│   └── Handlers/
│       ├── CreateProductCommandHandler.php
│       ├── UpdateProductCommandHandler.php
│       ├── GetProductQueryHandler.php
│       └── ListProductsQueryHandler.php
└── Infrastructure/
    ├── Http/Controllers/
    │   └── ProductController.php
    └── Persistence/
        └── InMemoryProductRepository.php
```

---

## Step 1: Create the Value Object

Value objects are immutable objects that represent concepts in your domain. Money is a classic example—it has an amount and currency, and two Money objects are equal if they have the same values.

Create `src/Domain/ValueObjects/Money.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use Luminor\DDD\Domain\Abstractions\ValueObject;

final class Money extends ValueObject
{
    private function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code');
        }
    }

    /**
     * Create money from cents.
     */
    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, strtoupper($currency));
    }

    /**
     * Create money from dollars (convenience method).
     */
    public static function fromDollars(float $dollars, string $currency = 'USD'): self
    {
        return new self((int) round($dollars * 100), strtoupper($currency));
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Add two Money objects together.
     */
    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot add money with different currencies');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Format as human-readable string.
     */
    public function format(): string
    {
        $dollars = $this->amount / 100;
        return sprintf('%s %.2f', $this->currency, $dollars);
    }

    /**
     * Required by ValueObject base class for equality comparison.
     */
    protected function getEqualityComponents(): array
    {
        return [$this->amount, $this->currency];
    }
}
```

{: .note }

> **Why use cents instead of dollars?** Storing money as integers (cents) avoids floating-point precision issues. `$19.99` becomes `1999` cents.

---

## Step 2: Create the Entity

Entities are objects with a unique identity that persists over time. A Product has an ID that distinguishes it from other products, even if they have the same name and price.

Create `src/Domain/Entities/Product.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use Luminor\DDD\Domain\Abstractions\Entity;
use App\Domain\ValueObjects\Money;

final class Product extends Entity
{
    public function __construct(
        string $id,
        private string $name,
        private string $description,
        private Money $price,
        private int $stock,
    ) {
        parent::__construct($id);
    }

    /**
     * Factory method to create a new product.
     */
    public static function create(
        string $name,
        string $description,
        Money $price,
        int $stock = 0,
    ): self {
        return new self(
            self::generateId(),
            $name,
            $description,
            $price,
            $stock,
        );
    }

    // Getters
    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    // Domain behavior
    public function updateDetails(string $name, string $description): void
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function updatePrice(Money $price): void
    {
        $this->price = $price;
    }

    public function adjustStock(int $quantity): void
    {
        $newStock = $this->stock + $quantity;

        if ($newStock < 0) {
            throw new \InvalidArgumentException('Stock cannot be negative');
        }

        $this->stock = $newStock;
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }
}
```

{: .highlight }

> Notice how the entity encapsulates business rules. The `adjustStock()` method ensures stock never goes negative—this is domain logic that belongs in the entity, not in a controller or service.

---

## Step 3: Define the Repository Interface

The repository pattern abstracts data persistence. We define an interface in the domain layer, keeping it independent of infrastructure concerns.

Create `src/Domain/Repository/ProductRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entities\Product;

interface ProductRepositoryInterface
{
    public function findById(string $id): ?Product;

    /**
     * @return Product[]
     */
    public function findAll(int $offset = 0, int $limit = 50): array;

    public function count(): int;

    public function save(Product $product): void;

    public function delete(Product $product): void;
}
```

---

## Step 4: Create Commands and Queries (CQRS)

CQRS separates read operations (Queries) from write operations (Commands). This provides clarity and enables optimization of each path independently.

### Create Product Command

Create `src/Application/Commands/CreateProductCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Commands;

use Luminor\DDD\Application\CQRS\Command;

final class CreateProductCommand implements Command
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceInCents,
        public readonly string $currency = 'USD',
        public readonly int $stock = 0,
    ) {
    }
}
```

### Update Product Command

Create `src/Application/Commands/UpdateProductCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Commands;

use Luminor\DDD\Application\CQRS\Command;

final class UpdateProductCommand implements Command
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?int $priceInCents = null,
        public readonly ?string $currency = null,
        public readonly ?int $stock = null,
    ) {
    }
}
```

### Get Product Query

Create `src/Application/Queries/GetProductQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Luminor\DDD\Application\CQRS\Query;

final class GetProductQuery implements Query
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
```

### List Products Query

Create `src/Application/Queries/ListProductsQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Luminor\DDD\Application\CQRS\Query;

final class ListProductsQuery implements Query
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {
    }
}
```

---

## Step 5: Create Command and Query Handlers

Handlers contain the actual logic for processing commands and queries.

### Create Product Handler

Create `src/Application/Handlers/CreateProductCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Commands\CreateProductCommand;
use App\Domain\Entities\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObjects\Money;
use Luminor\DDD\Application\Bus\CommandHandlerInterface;

final class CreateProductCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(CreateProductCommand $command): string
    {
        $product = Product::create(
            name: $command->name,
            description: $command->description,
            price: Money::fromCents($command->priceInCents, $command->currency),
            stock: $command->stock,
        );

        $this->repository->save($product);

        return $product->getId();
    }
}
```

### Update Product Handler

Create `src/Application/Handlers/UpdateProductCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Commands\UpdateProductCommand;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObjects\Money;
use Luminor\DDD\Application\Bus\CommandHandlerInterface;

final class UpdateProductCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(UpdateProductCommand $command): bool
    {
        $product = $this->repository->findById($command->id);

        if ($product === null) {
            return false;
        }

        if ($command->name !== null || $command->description !== null) {
            $product->updateDetails(
                $command->name ?? $product->getName(),
                $command->description ?? $product->getDescription(),
            );
        }

        if ($command->priceInCents !== null) {
            $product->updatePrice(
                Money::fromCents(
                    $command->priceInCents,
                    $command->currency ?? $product->getPrice()->getCurrency()
                )
            );
        }

        if ($command->stock !== null) {
            $stockDiff = $command->stock - $product->getStock();
            $product->adjustStock($stockDiff);
        }

        $this->repository->save($product);

        return true;
    }
}
```

### Get Product Handler

Create `src/Application/Handlers/GetProductQueryHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Queries\GetProductQuery;
use App\Domain\Repository\ProductRepositoryInterface;
use Luminor\DDD\Application\Bus\QueryHandlerInterface;

final class GetProductQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetProductQuery $query): ?array
    {
        $product = $this->repository->findById($query->id);

        if ($product === null) {
            return null;
        }

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice()->getAmount(),
            'currency' => $product->getPrice()->getCurrency(),
            'stock' => $product->getStock(),
            'in_stock' => $product->isInStock(),
        ];
    }
}
```

### List Products Handler

Create `src/Application/Handlers/ListProductsQueryHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Queries\ListProductsQuery;
use App\Domain\Repository\ProductRepositoryInterface;
use Luminor\DDD\Application\Bus\QueryHandlerInterface;
use Luminor\DDD\Application\DTO\PaginatedResult;

final class ListProductsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    public function __invoke(ListProductsQuery $query): PaginatedResult
    {
        $offset = ($query->page - 1) * $query->perPage;
        $products = $this->repository->findAll($offset, $query->perPage);
        $total = $this->repository->count();

        $items = array_map(fn($product) => [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice()->getAmount(),
            'currency' => $product->getPrice()->getCurrency(),
            'stock' => $product->getStock(),
        ], $products);

        return new PaginatedResult(
            items: $items,
            page: $query->page,
            perPage: $query->perPage,
            total: $total,
        );
    }
}
```

---

## Step 6: Implement the Repository

For this tutorial, we'll use an in-memory repository. In a real application, you'd implement a database-backed repository.

Create `src/Infrastructure/Persistence/InMemoryProductRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Product;
use App\Domain\Repository\ProductRepositoryInterface;

final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<string, Product> */
    private array $products = [];

    public function findById(string $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findAll(int $offset = 0, int $limit = 50): array
    {
        return array_slice(array_values($this->products), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->products);
    }

    public function save(Product $product): void
    {
        $this->products[$product->getId()] = $product;
    }

    public function delete(Product $product): void
    {
        unset($this->products[$product->getId()]);
    }
}
```

---

## Step 7: Create the Controller

The controller handles HTTP requests and delegates to the appropriate commands and queries.

Create `src/Infrastructure/Http/Controllers/ProductController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Commands\CreateProductCommand;
use App\Application\Commands\UpdateProductCommand;
use App\Application\Queries\GetProductQuery;
use App\Application\Queries\ListProductsQuery;
use Luminor\DDD\Application\Bus\CommandBusInterface;
use Luminor\DDD\Application\Bus\QueryBusInterface;
use Luminor\DDD\Infrastructure\Http\ApiController;
use Utopia\Http\Request;
use Utopia\Http\Response;

final class ProductController extends ApiController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    /**
     * GET /products
     */
    public function index(Request $request, Response $response): Response
    {
        $page = (int) ($request->getQuery('page', '1'));
        $perPage = (int) ($request->getQuery('per_page', '15'));

        $result = $this->queryBus->dispatch(new ListProductsQuery($page, $perPage));

        return $this->success($response, [
            'data' => $result->getItems(),
            'meta' => [
                'page' => $result->getPage(),
                'per_page' => $result->getPerPage(),
                'total' => $result->getTotal(),
                'total_pages' => $result->getTotalPages(),
            ],
        ]);
    }

    /**
     * GET /products/:id
     */
    public function show(Request $request, Response $response, string $id): Response
    {
        $product = $this->queryBus->dispatch(new GetProductQuery($id));

        if ($product === null) {
            return $this->notFound($response, 'Product not found');
        }

        return $this->success($response, ['data' => $product]);
    }

    /**
     * POST /products
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getPayload();

        // Validate input
        $errors = [];
        if (empty($data['name'])) {
            $errors['name'] = ['Name is required'];
        }
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors['price'] = ['Price is required and must be a number'];
        }

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        $productId = $this->commandBus->dispatch(new CreateProductCommand(
            name: $data['name'],
            description: $data['description'] ?? '',
            priceInCents: (int) $data['price'],
            currency: $data['currency'] ?? 'USD',
            stock: (int) ($data['stock'] ?? 0),
        ));

        return $this->created($response, [
            'data' => ['id' => $productId],
            'message' => 'Product created successfully',
        ]);
    }

    /**
     * PUT /products/:id
     */
    public function update(Request $request, Response $response, string $id): Response
    {
        $data = $request->getPayload();

        $success = $this->commandBus->dispatch(new UpdateProductCommand(
            id: $id,
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            priceInCents: isset($data['price']) ? (int) $data['price'] : null,
            currency: $data['currency'] ?? null,
            stock: isset($data['stock']) ? (int) $data['stock'] : null,
        ));

        if (!$success) {
            return $this->notFound($response, 'Product not found');
        }

        return $this->success($response, ['message' => 'Product updated successfully']);
    }
}
```

---

## Step 8: Wire It All Together

Create your entry point at `public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Commands\CreateProductCommand;
use App\Application\Commands\UpdateProductCommand;
use App\Application\Handlers\CreateProductCommandHandler;
use App\Application\Handlers\UpdateProductCommandHandler;
use App\Application\Handlers\GetProductQueryHandler;
use App\Application\Handlers\ListProductsQueryHandler;
use App\Application\Queries\GetProductQuery;
use App\Application\Queries\ListProductsQuery;
use App\Infrastructure\Http\Controllers\ProductController;
use App\Infrastructure\Persistence\InMemoryProductRepository;
use Luminor\DDD\Infrastructure\Bus\SimpleCommandBus;
use Luminor\DDD\Infrastructure\Bus\SimpleQueryBus;
use Utopia\Http\Http;

// Create HTTP instance
$http = Http::getInstance();

// Set up repository
$productRepository = new InMemoryProductRepository();

// Set up command bus
$commandBus = new SimpleCommandBus();
$commandBus->registerHandler(
    CreateProductCommand::class,
    new CreateProductCommandHandler($productRepository)
);
$commandBus->registerHandler(
    UpdateProductCommand::class,
    new UpdateProductCommandHandler($productRepository)
);

// Set up query bus
$queryBus = new SimpleQueryBus();
$queryBus->registerHandler(
    GetProductQuery::class,
    new GetProductQueryHandler($productRepository)
);
$queryBus->registerHandler(
    ListProductsQuery::class,
    new ListProductsQueryHandler($productRepository)
);

// Create controller
$productController = new ProductController($commandBus, $queryBus);

// Define routes
$http->get('/products')
    ->inject('request')
    ->inject('response')
    ->action(fn($request, $response) => $productController->index($request, $response));

$http->get('/products/:id')
    ->param('id', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->action(fn($id, $request, $response) => $productController->show($request, $response, $id));

$http->post('/products')
    ->inject('request')
    ->inject('response')
    ->action(fn($request, $response) => $productController->store($request, $response));

$http->put('/products/:id')
    ->param('id', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->action(fn($id, $request, $response) => $productController->update($request, $response, $id));

// Run the application
$http->run();
```

---

## Step 9: Test Your API

Start the development server:

```bash
vendor/bin/luminor serve
```

### Create a Product

```bash
curl -X POST http://localhost:8080/products \
  -H "Content-Type: application/json" \
  -d '{"name": "Widget", "description": "A useful widget", "price": 1999, "stock": 100}'
```

Response:

```json
{
  "data": { "id": "550e8400-e29b-41d4-a716-446655440000" },
  "message": "Product created successfully"
}
```

### List Products

```bash
curl http://localhost:8080/products
```

### Get a Product

```bash
curl http://localhost:8080/products/550e8400-e29b-41d4-a716-446655440000
```

### Update a Product

```bash
curl -X PUT http://localhost:8080/products/550e8400-e29b-41d4-a716-446655440000 \
  -H "Content-Type: application/json" \
  -d '{"name": "Super Widget", "price": 2499}'
```

---

## Summary

Congratulations! You've built a complete REST API using Luminor's DDD architecture. You've learned:

✅ **Value Objects** - Immutable objects for domain concepts (Money)  
✅ **Entities** - Objects with identity and lifecycle (Product)  
✅ **Repository Pattern** - Abstract data persistence  
✅ **CQRS** - Separate commands (writes) from queries (reads)  
✅ **Handlers** - Process commands and queries  
✅ **Controllers** - Handle HTTP requests

## Next Steps

- Add delete functionality with a `DeleteProductCommand`
- Implement database persistence using Doctrine
- Add authentication middleware
- Explore [Modules](../modules) for organizing larger applications
- Check out [Testing](../testing) to write tests for your handlers
