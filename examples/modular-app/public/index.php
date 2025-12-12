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
use Utopia\Http\Http;
use Utopia\Http\Response;

// Create the HTTP instance
$http = Http::getInstance();
$http->setMode(Http::MODE_DEFAULT);

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

$http->get('/catalog/products')
    ->inject('request')
    ->inject('response')
    ->action(function ($request, Response $response) use ($productRepository) {
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
        
        return $response->json(['data' => $data]);
    });

$http->get('/catalog/products/:id')
    ->param('id', '', 'string', 'Product ID')
    ->inject('response')
    ->action(function ($id, Response $response) use ($productRepository) {
        $product = $productRepository->findById($id);
        
        if ($product === null) {
            $response->setStatusCode(404);
            return $response->json(['error' => 'Product not found']);
        }
        
        return $response->json([
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

$http->post('/catalog/products')
    ->inject('request')
    ->inject('response')
    ->action(function ($request, Response $response) use ($productRepository) {
        $data = $request->getPayload();
        
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
        return $response->json([
            'data' => ['id' => $product->getId()],
            'message' => 'Product created'
        ]);
    });

// ============================================
// INVENTORY MODULE ROUTES
// ============================================

$http->get('/inventory/:productId')
    ->param('productId', '', 'string', 'Product ID')
    ->inject('response')
    ->action(function ($productId, Response $response) use ($stockRepository) {
        $stock = $stockRepository->findByProductId($productId);
        
        if ($stock === null) {
            $response->setStatusCode(404);
            return $response->json(['error' => 'Stock record not found']);
        }
        
        return $response->json([
            'data' => [
                'productId' => $stock->getProductId(),
                'quantity' => $stock->getQuantity(),
                'reserved' => $stock->getReservedQuantity(),
                'available' => $stock->getAvailableQuantity(),
            ]
        ]);
    });

$http->post('/inventory/:productId/adjust')
    ->param('productId', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->action(function ($productId, $request, Response $response) use ($stockRepository) {
        $stock = $stockRepository->findByProductId($productId);
        
        if ($stock === null) {
            // Create new stock record
            $stock = Stock::create($productId, 0);
        }
        
        $data = $request->getPayload();
        $adjustment = (int) ($data['adjustment'] ?? 0);
        $reason = $data['reason'] ?? '';
        
        try {
            $stock->adjust($adjustment, $reason);
            $stockRepository->save($stock);
            
            return $response->json([
                'message' => 'Stock adjusted',
                'data' => [
                    'quantity' => $stock->getQuantity(),
                    'available' => $stock->getAvailableQuantity(),
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            $response->setStatusCode(400);
            return $response->json(['error' => $e->getMessage()]);
        }
    });

// ============================================
// ORDERS MODULE ROUTES
// ============================================

$http->get('/orders')
    ->inject('request')
    ->inject('response')
    ->action(function ($request, Response $response) use ($orderRepository) {
        $orders = $orderRepository->findAll();
        
        $data = array_map(fn($o) => [
            'id' => $o->getId(),
            'customerId' => $o->getCustomerId(),
            'status' => $o->getStatus()->value,
            'total' => $o->getTotalInCents(),
            'lineCount' => count($o->getLines()),
            'createdAt' => $o->getCreatedAt()->format('c'),
        ], $orders);
        
        return $response->json(['data' => $data]);
    });

$http->get('/orders/:id')
    ->param('id', '', 'string', 'Order ID')
    ->inject('response')
    ->action(function ($id, Response $response) use ($orderRepository) {
        $order = $orderRepository->findById($id);
        
        if ($order === null) {
            $response->setStatusCode(404);
            return $response->json(['error' => 'Order not found']);
        }
        
        $lines = array_map(fn($l) => [
            'productId' => $l->getProductId(),
            'productName' => $l->getProductName(),
            'quantity' => $l->getQuantity(),
            'unitPrice' => $l->getUnitPriceInCents(),
            'total' => $l->getTotalInCents(),
        ], $order->getLines());
        
        return $response->json([
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

$http->post('/orders')
    ->inject('request')
    ->inject('response')
    ->action(function ($request, Response $response) use ($orderRepository, $productRepository, $stockRepository) {
        $data = $request->getPayload();
        
        $order = Order::place($data['customerId'] ?? 'anonymous');
        
        foreach ($data['items'] ?? [] as $item) {
            $product = $productRepository->findById($item['productId']);
            
            if ($product === null) {
                $response->setStatusCode(400);
                return $response->json(['error' => "Product {$item['productId']} not found"]);
            }
            
            // Check inventory
            $stock = $stockRepository->findByProductId($item['productId']);
            if ($stock === null || !$stock->isAvailable($item['quantity'])) {
                $response->setStatusCode(400);
                return $response->json(['error' => "Insufficient stock for {$product->getName()}"]);
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
            return $response->json([
                'data' => ['id' => $order->getId()],
                'message' => 'Order placed successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            $response->setStatusCode(400);
            return $response->json(['error' => $e->getMessage()]);
        }
    });

// Error handling
$http->error()
    ->inject('error')
    ->inject('response')
    ->action(function ($error, Response $response) {
        $response->setStatusCode(500);
        return $response->json([
            'error' => 'Internal Server Error',
            'message' => $error->getMessage(),
        ]);
    });

// Start the application
$http->start();
