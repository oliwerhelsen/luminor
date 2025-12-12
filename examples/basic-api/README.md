# Basic API Example

A simple REST API demonstrating the Lumina DDD Framework fundamentals.

## Structure

```
basic-api/
├── config/
│   └── framework.php
├── src/
│   ├── Domain/
│   │   ├── Entities/
│   │   │   └── Product.php
│   │   ├── Repository/
│   │   │   └── ProductRepositoryInterface.php
│   │   └── ValueObjects/
│   │       └── Money.php
│   ├── Application/
│   │   ├── Commands/
│   │   │   ├── CreateProductCommand.php
│   │   │   └── UpdateProductCommand.php
│   │   ├── Queries/
│   │   │   ├── GetProductQuery.php
│   │   │   └── ListProductsQuery.php
│   │   ├── Handlers/
│   │   │   └── ...
│   │   └── DTOs/
│   │       └── ProductDto.php
│   └── Infrastructure/
│       ├── Http/
│       │   └── Controllers/
│       │       └── ProductController.php
│       └── Persistence/
│           └── InMemoryProductRepository.php
├── public/
│   └── index.php
└── tests/
    └── ...
```

## Features Demonstrated

- Entity with value object
- CQRS pattern (Commands and Queries)
- Repository pattern
- API Controller with standard responses
- Input validation

## Running the Example

```bash
cd examples/basic-api
composer install
php -S localhost:8080 -t public/
```

## API Endpoints

- `GET /products` - List all products
- `GET /products/:id` - Get a single product
- `POST /products` - Create a new product
- `PUT /products/:id` - Update a product
- `DELETE /products/:id` - Delete a product
