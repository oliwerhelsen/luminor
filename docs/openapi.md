---
title: OpenAPI Documentation
layout: default
nav_order: 12
description: "Generate OpenAPI/Swagger documentation for your API"
permalink: /openapi/
---

# OpenAPI Documentation

Generate beautiful, interactive API documentation automatically using OpenAPI (Swagger) specifications.

{: .highlight }
New in v2.2: Swagger UI as the default index page in development mode—inspired by Laravel Scramble!

## Table of Contents

- [Introduction](#introduction)
- [Development Mode Swagger UI](#development-mode-swagger-ui)
- [Quick Start](#quick-start)
- [Documenting Endpoints](#documenting-endpoints)
- [Schemas](#schemas)
- [Security](#security)
- [Generating Documentation](#generating-documentation)

---

## Introduction

OpenAPI (formerly Swagger) is the industry standard for documenting REST APIs. Luminor provides automatic OpenAPI generation using PHP attributes.

**Benefits:**

- Interactive API explorer (Swagger UI)
- Client SDK generation
- API testing tools
- Clear contracts between frontend and backend

---

## Development Mode Swagger UI

{: .new }
New in v2.2: Enable interactive API documentation at your root URL during development.

In development mode, you can automatically serve Swagger UI at your application's index page (`/`). This makes it easy to explore and test your API without any additional setup.

### Enable Development Docs

Add one line to your entry point to enable Swagger UI:

```php
<?php

use Luminor\Http\HttpKernel;

$http = HttpKernel::getInstance();

// Load your config
$config = require __DIR__ . '/../config/framework.php';

// Enable Swagger UI at "/" in development mode
if ($config['debug'] ?? false) {
    $http->enableDevelopmentDocs($config);
}

// Register your routes...
$http->get('/products', [ProductController::class, 'index']);
// ...

$http->run();
```

### Configuration

Configure the Swagger UI in your `config/framework.php`:

```php
return [
    'name' => 'My API',
    'debug' => true, // Enable development mode

    'openapi' => [
        'info' => [
            'title' => 'My API Documentation',
            'version' => '1.0.0',
            'description' => 'Interactive API documentation',
        ],
        'spec_path' => '/api/openapi.json',
        'servers' => [
            [
                'url' => 'http://localhost:8000',
                'description' => 'Development server',
            ],
        ],
    ],
];
```

### Features

- **Interactive Try It Out** - Test endpoints directly from the documentation
- **Beautiful UI** - Modern Swagger UI with dark mode support
- **Development Badge** - Visual indicator that you're in development mode
- **Auto-refresh** - Spec is always up-to-date with your code
- **Zero Configuration** - Works out of the box with sensible defaults

---

## Quick Start

### 1. Document Your Controller

```php
<?php

use Luminor\Http\Controllers\ApiController;
use Luminor\Http\OpenApi\Attributes\OpenApiOperation;
use Luminor\Http\OpenApi\Attributes\OpenApiParameter;
use Luminor\Http\OpenApi\Attributes\OpenApiResponse;
use Luminor\Http\OpenApi\Attributes\OpenApiRequestBody;

final class ProductController extends ApiController
{
    #[OpenApiOperation(
        summary: 'List all products',
        description: 'Returns a paginated list of products',
        tags: ['Products']
    )]
    #[OpenApiParameter(
        name: 'page',
        in: 'query',
        description: 'Page number',
        required: false,
        type: 'integer',
        example: 1
    )]
    #[OpenApiParameter(
        name: 'per_page',
        in: 'query',
        description: 'Items per page',
        required: false,
        type: 'integer',
        example: 20
    )]
    #[OpenApiResponse(
        statusCode: 200,
        description: 'Success',
        schema: 'ProductList'
    )]
    public function index(Request $request, Response $response): Response
    {
        // Implementation
    }

    #[OpenApiOperation(
        summary: 'Create a new product',
        description: 'Creates a new product and returns its ID',
        tags: ['Products']
    )]
    #[OpenApiRequestBody(
        description: 'Product data',
        required: true,
        schema: 'CreateProductRequest'
    )]
    #[OpenApiResponse(
        statusCode: 201,
        description: 'Product created',
        schema: 'Product'
    )]
    #[OpenApiResponse(
        statusCode: 422,
        description: 'Validation error'
    )]
    public function store(Request $request, Response $response): Response
    {
        // Implementation
    }
}
```

### 2. Generate Documentation

```bash
# Generate JSON
php luminor openapi:generate --output=public/openapi.json

# Generate YAML
php luminor openapi:generate --format=yaml --output=public/openapi.yaml
```

### 3. View with Swagger UI

Include Swagger UI in your HTML:

```html
<!DOCTYPE html>
<html>
  <head>
    <title>API Documentation</title>
    <link
      rel="stylesheet"
      href="https://unpkg.com/swagger-ui-dist/swagger-ui.css"
    />
  </head>
  <body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
    <script>
      SwaggerUIBundle({
        url: "/openapi.json",
        dom_id: "#swagger-ui",
      });
    </script>
  </body>
</html>
```

---

## Documenting Endpoints

### Operation Attributes

```php
#[OpenApiOperation(
    summary: 'Short description',
    description: 'Longer detailed description',
    tags: ['Category'],
    deprecated: false
)]
```

### Parameters

**Query Parameters:**

```php
#[OpenApiParameter(
    name: 'search',
    in: 'query',
    description: 'Search term',
    required: false,
    type: 'string'
)]
```

**Path Parameters:**

```php
#[OpenApiParameter(
    name: 'id',
    in: 'path',
    description: 'Product ID',
    required: true,
    type: 'string'
)]
```

**Header Parameters:**

```php
#[OpenApiParameter(
    name: 'X-API-Key',
    in: 'header',
    description: 'API authentication key',
    required: true,
    type: 'string'
)]
```

### Request Bodies

```php
#[OpenApiRequestBody(
    description: 'Product creation data',
    required: true,
    schema: 'CreateProductRequest',
    example: [
        'name' => 'Widget',
        'price' => 1999,
        'sku' => 'WID-001'
    ]
)]
```

### Responses

```php
#[OpenApiResponse(
    statusCode: 200,
    description: 'Successful response',
    schema: 'Product',
    example: [
        'id' => 'prod-123',
        'name' => 'Widget',
        'price' => 1999
    ]
)]
#[OpenApiResponse(
    statusCode: 404,
    description: 'Product not found'
)]
```

---

## Schemas

### Defining Schemas

```php
<?php

use Luminor\Http\OpenApi\OpenApiGenerator;

$generator = new OpenApiGenerator('My API', '1.0.0');

$generator->addSchema('Product', [
    'type' => 'object',
    'properties' => [
        'id' => [
            'type' => 'string',
            'format' => 'uuid',
            'example' => 'prod-123',
        ],
        'name' => [
            'type' => 'string',
            'example' => 'Widget',
        ],
        'price' => [
            'type' => 'integer',
            'description' => 'Price in cents',
            'example' => 1999,
        ],
        'sku' => [
            'type' => 'string',
            'example' => 'WID-001',
        ],
    ],
    'required' => ['name', 'price', 'sku'],
]);

$generator->addSchema('CreateProductRequest', [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'price' => ['type' => 'integer'],
        'sku' => ['type' => 'string'],
    ],
    'required' => ['name', 'price', 'sku'],
]);
```

### Array Schemas

```php
$generator->addSchema('ProductList', [
    'type' => 'object',
    'properties' => [
        'data' => [
            'type' => 'array',
            'items' => [
                '$ref' => '#/components/schemas/Product'
            ],
        ],
        'meta' => [
            'type' => 'object',
            'properties' => [
                'total' => ['type' => 'integer'],
                'page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
            ],
        ],
    ],
]);
```

---

## Security

### JWT Authentication

```php
<?php

$generator->addSecurityScheme('bearerAuth', [
    'type' => 'http',
    'scheme' => 'bearer',
    'bearerFormat' => 'JWT',
]);
```

### API Key

```php
$generator->addSecurityScheme('apiKey', [
    'type' => 'apiKey',
    'in' => 'header',
    'name' => 'X-API-Key',
]);
```

### OAuth2

```php
$generator->addSecurityScheme('oauth2', [
    'type' => 'oauth2',
    'flows' => [
        'authorizationCode' => [
            'authorizationUrl' => 'https://example.com/oauth/authorize',
            'tokenUrl' => 'https://example.com/oauth/token',
            'scopes' => [
                'read' => 'Read access',
                'write' => 'Write access',
            ],
        ],
    ],
]);
```

---

## Generating Documentation

### CLI Command

```bash
# JSON format (default)
php luminor openapi:generate --output=public/openapi.json

# YAML format
php luminor openapi:generate --format=yaml --output=public/openapi.yaml

# Print to stdout
php luminor openapi:generate
```

### Programmatic Generation

```php
<?php

use Luminor\Http\OpenApi\OpenApiGenerator;

$generator = new OpenApiGenerator(
    title: 'My API',
    version: '1.0.0',
    description: 'API for my application'
);

// Add server
$generator->addServer('https://api.example.com', 'Production');
$generator->addServer('https://staging.api.example.com', 'Staging');

// Add security
$generator->addSecurityScheme('bearerAuth', [
    'type' => 'http',
    'scheme' => 'bearer',
    'bearerFormat' => 'JWT',
]);

// Add routes
$generator->addRoute(
    method: 'GET',
    path: '/products',
    summary: 'List products',
    description: 'Returns a paginated list of products',
    parameters: [
        [
            'name' => 'page',
            'in' => 'query',
            'schema' => ['type' => 'integer'],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Success',
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/ProductList'],
                ],
            ],
        ],
    ]
);

// Generate
$json = $generator->toJson();
file_put_contents('public/openapi.json', $json);
```

---

## Best Practices

### Documentation Quality

✅ **Good:**

- Descriptive summaries
- Complete examples
- All required parameters documented
- Error responses included

❌ **Bad:**

- Missing descriptions
- No examples
- Incomplete parameter documentation

### Versioning

Include API version:

```php
$generator = new OpenApiGenerator('My API', '2.0.0');
```

### Tags

Group related endpoints:

```php
#[OpenApiOperation(
    summary: 'Create product',
    tags: ['Products', 'Catalog']
)]
```

### Examples

Provide realistic examples:

```php
#[OpenApiResponse(
    statusCode: 200,
    schema: 'Product',
    example: [
        'id' => 'prod-a1b2c3',
        'name' => 'Ergonomic Keyboard',
        'price' => 7999,
        'sku' => 'KEY-ERG-001',
        'in_stock' => true
    ]
)]
```

---

## Next Steps

- [HTTP Layer](http-layer)
- [Controllers](http-layer#controllers)
- [API Best Practices](best-practices#http-layer-standards)
