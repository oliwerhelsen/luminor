---
title: HTTP Layer
layout: default
parent: Core Concepts
nav_order: 3
description: "Controllers, Routes, Middleware, and Request/Response handling"
---

# HTTP Layer

The HTTP layer integrates with Utopia PHP to provide a clean API for your domain-driven application.

## Controllers

### Base API Controller

The `ApiController` provides common helpers for API responses:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use Luminor\DDD\Infrastructure\Http\ApiController;
use Utopia\Http\Request;
use Utopia\Http\Response;

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

use Utopia\Http\Http;

$http = Http::getInstance();

// GET request
$http->get('/products')
    ->inject('request')
    ->inject('response')
    ->inject('productController')
    ->action(function ($request, $response, $controller) {
        return $controller->index($request, $response);
    });

// GET with parameter
$http->get('/products/:id')
    ->param('id', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->inject('productController')
    ->action(function ($id, $request, $response, $controller) {
        return $controller->show($request, $response, $id);
    });

// POST request
$http->post('/products')
    ->inject('request')
    ->inject('response')
    ->inject('productController')
    ->action(function ($request, $response, $controller) {
        return $controller->store($request, $response);
    });
```

### Route Groups

Use the RouteRegistrar for cleaner route definitions:

```php
<?php

use Luminor\DDD\Infrastructure\Http\RouteRegistrar;

$registrar = new RouteRegistrar($http);

$registrar->group('/api/v1', function (RouteRegistrar $routes) {
    $routes->resource('products', ProductController::class);
    $routes->resource('orders', OrderController::class);

    $routes->group('/admin', function (RouteRegistrar $routes) {
        $routes->resource('users', AdminUserController::class);
    });
});
```

## Middleware

### Creating Middleware

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Luminor\DDD\Infrastructure\Http\Middleware\MiddlewareInterface;
use Utopia\Http\Request;
use Utopia\Http\Response;

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

$handler = new ExceptionHandler(debug: $config['app']['debug']);

// Register custom handlers
$handler->register(ValidationException::class, function ($e, $response) {
    return $response
        ->setStatusCode(422)
        ->json([
            'error' => 'Validation failed',
            'errors' => $e->getErrors(),
        ]);
});

$handler->register(DomainException::class, function ($e, $response) {
    return $response
        ->setStatusCode(400)
        ->json([
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
});

// Use in error handling
$http->error()
    ->inject('error')
    ->inject('response')
    ->action(function ($error, $response) use ($handler) {
        return $handler->handle($error, $response);
    });
```

## Request Validation

### Using Validation Middleware

```php
<?php

use Luminor\DDD\Infrastructure\Http\Middleware\ValidationMiddleware;

$validation = new ValidationMiddleware([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email'],
    'password' => ['required', 'string', 'min:8'],
]);

$http->post('/users')
    ->middleware($validation)
    ->inject('request')
    ->inject('response')
    ->action(function ($request, $response) {
        // Request is already validated
    });
```

## Best Practices

1. **Use thin controllers**: Controllers should delegate to the application layer
2. **Return consistent responses**: Use the response helpers for uniform API responses
3. **Handle errors gracefully**: Use the exception handler for global error handling
4. **Validate input**: Always validate request data before processing
5. **Use middleware**: Extract cross-cutting concerns into middleware
6. **Version your API**: Use route prefixes like `/api/v1`
