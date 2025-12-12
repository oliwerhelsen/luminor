# Modular Application Example

A modular e-commerce application demonstrating the Lumina DDD Framework's module system.

## Structure

```
modular-app/
├── config/
│   └── framework.php
├── src/
│   └── Modules/
│       ├── Catalog/            # Product catalog module
│       │   ├── Domain/
│       │   ├── Application/
│       │   ├── Infrastructure/
│       │   └── CatalogModule.php
│       ├── Inventory/          # Inventory management module
│       │   ├── Domain/
│       │   ├── Application/
│       │   ├── Infrastructure/
│       │   └── InventoryModule.php
│       └── Orders/             # Order management module
│           ├── Domain/
│           ├── Application/
│           ├── Infrastructure/
│           └── OrdersModule.php
├── public/
│   └── index.php
└── tests/
    └── ...
```

## Features Demonstrated

- Modular architecture with separate bounded contexts
- Cross-module communication via events
- Each module has its own domain, application, and infrastructure layers
- Module service providers for dependency injection
- Aggregate roots with domain events
- Multi-tenant support (optional)

## Modules

### Catalog Module
Manages the product catalog with categories and products.

### Inventory Module
Tracks stock levels and handles inventory adjustments. Listens to order events.

### Orders Module
Handles customer orders. Publishes events when orders are placed.

## Running the Example

```bash
cd examples/modular-app
composer install
php -S localhost:8080 -t public/
```

## API Endpoints

### Catalog
- `GET /catalog/products` - List products
- `GET /catalog/products/:id` - Get product details
- `POST /catalog/products` - Create product

### Inventory
- `GET /inventory/:productId` - Get stock level
- `POST /inventory/:productId/adjust` - Adjust stock

### Orders
- `GET /orders` - List orders
- `GET /orders/:id` - Get order details
- `POST /orders` - Place new order
