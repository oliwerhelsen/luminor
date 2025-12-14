---
title: HTTP Layer
layout: default
parent: Core Concepts
nav_order: 3
description: "Controllers, Routes, Middleware, and Request/Response handling"
---

# HTTP Layer

The HTTP layer provides a clean, expressive API for building REST APIs with your domain-driven application. Built on Symfony HttpFoundation, it offers flexibility to run on PHP-FPM, Swoole, or FrankenPHP.

## Controllers

### Base API Controller

The `ApiController` provides common helpers for API responses:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use Luminor\DDD\Infrastructure\Http\ApiController;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

final class ProductController extends ApiController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $products = $this->queryBus->dispatch(new ListProductsQuery(
            page: (int) $request->getQuery('page', '1'),
            perPage: (int) $request->getQuery('per_page', '15'),
        ));

        return $this->success($response, [
            'data' => $products->items,
            'meta' => [
                'page' => $products->page,
                'per_page' => $products->perPage,
                'total' => $products->total,
            ],
        ]);
    }

    public function show(Request $request, Response $response, string $id): Response
    {
        $product = $this->queryBus->dispatch(new GetProductQuery($id));

        if ($product === null) {
            return $this->notFound($response, 'Product not found');
        }

        return $this->success($response, ['data' => $product]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getPayload();

        $productId = $this->commandBus->dispatch(new CreateProductCommand(
            name: $data['name'] ?? '',
            price: $data['price'] ?? 0,
            description: $data['description'] ?? '',
        ));

        return $this->created($response, [
            'data' => ['id' => $productId],
            'message' => 'Product created successfully',
        ]);
    }
}
```

### Response Methods

The `ApiController` provides these response helpers:

```php
// 200 OK with data
$this->success($response, ['data' => $data]);

// 201 Created
$this->created($response, ['data' => $data]);

// 204 No Content
$this->noContent($response);

// 400 Bad Request
$this->badRequest($response, 'Invalid input');

// 401 Unauthorized
$this->unauthorized($response, 'Authentication required');

// 403 Forbidden
$this->forbidden($response, 'Access denied');

// 404 Not Found
$this->notFound($response, 'Resource not found');

// 422 Unprocessable Entity (validation errors)
$this->validationError($response, [
    'name' => ['Name is required'],
    'email' => ['Invalid email format'],
]);

// 500 Internal Server Error
$this->serverError($response, 'Something went wrong');
```

## Route Registration

### Basic Routes

```php
<?php

use Luminor\DDD\Http\Routing\Router;
use App\Infrastructure\Http\Controllers\ProductController;

$router = Router::getInstance();

// GET request
$router->get('/products', [ProductController::class, 'index']);

// GET with parameter
$router->get('/products/:id', [ProductController::class, 'show']);

// POST request
$router->post('/products', [ProductController::class, 'store']);

// PUT request
$router->put('/products/:id', [ProductController::class, 'update']);

// DELETE request
$router->delete('/products/:id', [ProductController::class, 'destroy']);
```

### Route Groups

Use route groups for shared attributes:

```php
<?php

use Luminor\DDD\Http\Routing\Router;
use App\Infrastructure\Http\Middleware\AuthMiddleware;

$router = Router::getInstance();

$router->group(['prefix' => '/api/v1', 'middleware' => [AuthMiddleware::class]], function (Router $r) {
    $r->resource('/products', ProductController::class);
    $r->resource('/orders', OrderController::class);

    $r->group(['prefix' => '/admin'], function (Router $r) {
        $r->resource('/users', AdminUserController::class);
    });
});
```

### Resource Routes

Register all CRUD routes with a single call:

```php
<?php

// Creates: GET /products, POST /products, GET /products/:id, PUT /products/:id, DELETE /products/:id
$router->resource('/products', ProductController::class);

// Only specific actions
$router->resource('/products', ProductController::class, ['only' => ['index', 'show']]);

// Except specific actions
$router->resource('/products', ProductController::class, ['except' => ['destroy']]);
```

## Middleware

### Creating Middleware

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Luminor\DDD\Infrastructure\Http\Middleware\MiddlewareInterface;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxRequests = 60,
    ) {
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $key = $this->getKey($request);

        if (!$this->limiter->attempt($key, $this->maxRequests)) {
            $response->setStatusCode(429);
            return $response->json([
                'error' => 'Too many requests',
                'retry_after' => $this->limiter->availableIn($key),
            ]);
        }

        return $next($request, $response);
    }

    private function getKey(Request $request): string
    {
        return 'rate_limit:' . ($request->getIP() ?? 'unknown');
    }
}
```

### Authentication Middleware

```php
<?php

use Luminor\DDD\Infrastructure\Http\Middleware\AbstractAuthMiddleware;

final class JwtAuthMiddleware extends AbstractAuthMiddleware
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    protected function authenticate(Request $request): ?AuthenticatableInterface
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return null;
        }

        $payload = $this->jwtService->verify($token);

        if ($payload === null) {
            return null;
        }

        return $this->userRepository->findById($payload['sub']);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->getHeader('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
```

### CORS Middleware

```php
<?php

use Luminor\DDD\Infrastructure\Http\Middleware\CorsMiddleware;

$cors = new CorsMiddleware(
    allowedOrigins: ['https://example.com', 'https://app.example.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization'],
    maxAge: 86400,
);
```

## Exception Handling

### Global Exception Handler

```php
<?php

use Luminor\DDD\Infrastructure\Http\ExceptionHandler;
use Luminor\DDD\Http\HttpKernel;

$handler = new ExceptionHandler(debug: $config['app']['debug']);

// Register custom handlers
$handler->register(ValidationException::class, function ($e, $response) {
    $response->setStatusCode(422);
    return $response->json([
        'error' => 'Validation failed',
        'errors' => $e->getErrors(),
    ]);
});

$handler->register(DomainException::class, function ($e, $response) {
    $response->setStatusCode(400);
    return $response->json([
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);
});

// Use in HttpKernel
$kernel = new HttpKernel($basePath);
$kernel->setExceptionHandler($handler);
$kernel->run();
```

## Request Validation

### Using Validation Middleware

```php
<?php

use Luminor\DDD\Infrastructure\Http\Middleware\ValidationMiddleware;
use Luminor\DDD\Http\Routing\Router;

$router = Router::getInstance();

// Apply validation middleware to route
$router->post('/users', [UserController::class, 'store'])
    ->middleware([
        new ValidationMiddleware([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]),
    ]);
```

### Controller-Level Validation

```php
<?php

final class UserController extends ApiController
{
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getPayload();

        // Manual validation
        $errors = [];
        if (empty($data['name'])) {
            $errors['name'] = ['Name is required'];
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Valid email is required'];
        }

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        // Process validated data...
    }
}
```

## Best Practices

1. **Use thin controllers**: Controllers should delegate to the application layer
2. **Return consistent responses**: Use the response helpers for uniform API responses
3. **Handle errors gracefully**: Use the exception handler for global error handling
4. **Validate input**: Always validate request data before processing
5. **Use middleware**: Extract cross-cutting concerns into middleware
6. **Version your API**: Use route prefixes like `/api/v1`
