<?php

declare(strict_types=1);

/**
 * Modular Application Example - Entry Point
 *
 * This demonstrates a modular e-commerce application with multiple bounded contexts.
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Example\ModularApp\Modules\Catalog\Domain\Entities\Product;
use Example\ModularApp\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;
use Example\ModularApp\Modules\Catalog\Domain\ValueObjects\Money;
use Example\ModularApp\Modules\Catalog\Infrastructure\Persistence\InMemoryProductRepository;
use Example\ModularApp\Modules\Inventory\Domain\Entities\Stock;
use Example\ModularApp\Modules\Inventory\Domain\Repository\StockRepositoryInterface;
use Example\ModularApp\Modules\Inventory\Infrastructure\Persistence\InMemoryStockRepository;
use Example\ModularApp\Modules\Orders\Domain\Entities\Order;
use Example\ModularApp\Modules\Orders\Domain\Repository\OrderRepositoryInterface;
use Example\ModularApp\Modules\Orders\Infrastructure\Persistence\InMemoryOrderRepository;
use Luminor\Http\HttpKernel;
use Luminor\Http\Request;
use Luminor\Http\Response;

// Create the HTTP kernel
$http = HttpKernel::getInstance();

// Initialize repositories (in a real app, these would be from DI container)
$productRepository = new InMemoryProductRepository();
$stockRepository = new InMemoryStockRepository();
$orderRepository = new InMemoryOrderRepository();

// Seed some sample data
$seedProducts = function() use ($productRepository, $stockRepository) {
    $products = [
        ['name' => 'Laptop', 'desc' => 'High-performance laptop', 'price' => 99999, 'category' => 'electronics', 'stock' => 10],
        ['name' => 'Mouse', 'desc' => 'Wireless mouse', 'price' => 2999, 'category' => 'electronics', 'stock' => 50],
        ['name' => 'Keyboard', 'desc' => 'Mechanical keyboard', 'price' => 14999, 'category' => 'electronics', 'stock' => 25],
    ];

    foreach ($products as $p) {
        $product = Product::create($p['name'], $p['desc'], Money::fromCents($p['price']), $p['category']);
        $productRepository->save($product);

        $stock = Stock::create($product->getId(), $p['stock']);
        $stockRepository->save($stock);
    }
};
$seedProducts();

// ============================================
// CATALOG MODULE ROUTES
// ============================================

$http->get('/catalog/products', function (Request $request, Response $response) use ($productRepository) {
    $products = $productRepository->findAll();

    $data = array_map(fn($p) => [
        'id' => $p->getId(),
        'name' => $p->getName(),
        'description' => $p->getDescription(),
        'price' => $p->getPrice()->getAmount(),
        'currency' => $p->getPrice()->getCurrency(),
        'category' => $p->getCategory(),
        'active' => $p->isActive(),
    ], $products);

    $response->json(['data' => $data]);
});

$http->get('/catalog/products/:id', function (Request $request, Response $response) use ($productRepository) {
    $id = $request->getAttribute('id');
    $product = $productRepository->findById($id);

    if ($product === null) {
        $response->setStatusCode(404);
        $response->json(['error' => 'Product not found']);
        return;
    }

    $response->json([
        'data' => [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice()->getAmount(),
            'currency' => $product->getPrice()->getCurrency(),
            'category' => $product->getCategory(),
            'active' => $product->isActive(),
        ]
    ]);
});

$http->post('/catalog/products', function (Request $request, Response $response) use ($productRepository) {
    $data = $request->getJsonPayload();

    $product = Product::create(
        $data['name'] ?? '',
        $data['description'] ?? '',
        Money::fromCents((int) ($data['price'] ?? 0), $data['currency'] ?? 'USD'),
        $data['category'] ?? 'general'
    );

    $productRepository->save($product);

    // Dispatch domain events here in a real app
    foreach ($product->pullEvents() as $event) {
        // $eventDispatcher->dispatch($event);
    }

    $response->setStatusCode(201);
    $response->json([
        'data' => ['id' => $product->getId()],
        'message' => 'Product created'
    ]);
});

// ============================================
// INVENTORY MODULE ROUTES
// ============================================

$http->get('/inventory/:productId', function (Request $request, Response $response) use ($stockRepository) {
    $productId = $request->getAttribute('productId');
    $stock = $stockRepository->findByProductId($productId);

    if ($stock === null) {
        $response->setStatusCode(404);
        $response->json(['error' => 'Stock record not found']);
        return;
    }

    $response->json([
        'data' => [
            'productId' => $stock->getProductId(),
            'quantity' => $stock->getQuantity(),
            'reserved' => $stock->getReservedQuantity(),
            'available' => $stock->getAvailableQuantity(),
        ]
    ]);
});

$http->post('/inventory/:productId/adjust', function (Request $request, Response $response) use ($stockRepository) {
    $productId = $request->getAttribute('productId');
    $stock = $stockRepository->findByProductId($productId);

    if ($stock === null) {
        // Create new stock record
        $stock = Stock::create($productId, 0);
    }

    $data = $request->getJsonPayload();
    $adjustment = (int) ($data['adjustment'] ?? 0);
    $reason = $data['reason'] ?? '';

    try {
        $stock->adjust($adjustment, $reason);
        $stockRepository->save($stock);

        $response->json([
            'message' => 'Stock adjusted',
            'data' => [
                'quantity' => $stock->getQuantity(),
                'available' => $stock->getAvailableQuantity(),
            ]
        ]);
    } catch (\InvalidArgumentException $e) {
        $response->setStatusCode(400);
        $response->json(['error' => $e->getMessage()]);
    }
});

// ============================================
// ORDERS MODULE ROUTES
// ============================================

$http->get('/orders', function (Request $request, Response $response) use ($orderRepository) {
    $orders = $orderRepository->findAll();

    $data = array_map(fn($o) => [
        'id' => $o->getId(),
        'customerId' => $o->getCustomerId(),
        'status' => $o->getStatus()->value,
        'total' => $o->getTotalInCents(),
        'lineCount' => count($o->getLines()),
        'createdAt' => $o->getCreatedAt()->format('c'),
    ], $orders);

    $response->json(['data' => $data]);
});

$http->get('/orders/:id', function (Request $request, Response $response) use ($orderRepository) {
    $id = $request->getAttribute('id');
    $order = $orderRepository->findById($id);

    if ($order === null) {
        $response->setStatusCode(404);
        $response->json(['error' => 'Order not found']);
        return;
    }

    $lines = array_map(fn($l) => [
        'productId' => $l->getProductId(),
        'productName' => $l->getProductName(),
        'quantity' => $l->getQuantity(),
        'unitPrice' => $l->getUnitPriceInCents(),
        'total' => $l->getTotalInCents(),
    ], $order->getLines());

    $response->json([
        'data' => [
            'id' => $order->getId(),
            'customerId' => $order->getCustomerId(),
            'status' => $order->getStatus()->value,
            'lines' => $lines,
            'total' => $order->getTotalInCents(),
            'createdAt' => $order->getCreatedAt()->format('c'),
        ]
    ]);
});

$http->post('/orders', function (Request $request, Response $response) use ($orderRepository, $productRepository, $stockRepository) {
    $data = $request->getJsonPayload();

    $order = Order::place($data['customerId'] ?? 'anonymous');

    foreach ($data['items'] ?? [] as $item) {
        $product = $productRepository->findById($item['productId']);

        if ($product === null) {
            $response->setStatusCode(400);
            $response->json(['error' => "Product {$item['productId']} not found"]);
            return;
        }

        // Check inventory
        $stock = $stockRepository->findByProductId($item['productId']);
        if ($stock === null || !$stock->isAvailable($item['quantity'])) {
            $response->setStatusCode(400);
            $response->json(['error' => "Insufficient stock for {$product->getName()}"]);
            return;
        }

        $order->addLine(
            $product->getId(),
            $product->getName(),
            (int) $item['quantity'],
            $product->getPrice()->getAmount()
        );
    }

    try {
        $order->submit();
        $orderRepository->save($order);

        // Reserve inventory
        foreach ($order->getLines() as $line) {
            $stock = $stockRepository->findByProductId($line->getProductId());
            $stock->reserve($line->getQuantity(), $order->getId());
            $stockRepository->save($stock);
        }

        // Dispatch domain events in real app
        foreach ($order->pullEvents() as $event) {
            // $eventDispatcher->dispatch($event);
        }

        $response->setStatusCode(201);
        $response->json([
            'data' => ['id' => $order->getId()],
            'message' => 'Order placed successfully'
        ]);
    } catch (\InvalidArgumentException $e) {
        $response->setStatusCode(400);
        $response->json(['error' => $e->getMessage()]);
    }
});

// Error handling
$http->onError(function (\Throwable $error, Request $request, Response $response) {
    $response->setStatusCode(500);
    $response->json([
        'error' => 'Internal Server Error',
        'message' => $error->getMessage(),
    ]);
});

// Run the application
$http->run();
