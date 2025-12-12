<?php

declare(strict_types=1);

/**
 * Basic API Example - Entry Point
 * 
 * This demonstrates a simple REST API using the Lumina DDD Framework.
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Example\BasicApi\Application\Commands\CreateProductCommand;
use Example\BasicApi\Application\Commands\DeleteProductCommand;
use Example\BasicApi\Application\Commands\UpdateProductCommand;
use Example\BasicApi\Application\Handlers\CreateProductCommandHandler;
use Example\BasicApi\Application\Handlers\DeleteProductCommandHandler;
use Example\BasicApi\Application\Handlers\GetProductQueryHandler;
use Example\BasicApi\Application\Handlers\ListProductsQueryHandler;
use Example\BasicApi\Application\Handlers\UpdateProductCommandHandler;
use Example\BasicApi\Application\Queries\GetProductQuery;
use Example\BasicApi\Application\Queries\ListProductsQuery;
use Example\BasicApi\Domain\Repository\ProductRepositoryInterface;
use Example\BasicApi\Infrastructure\Http\Controllers\ProductController;
use Example\BasicApi\Infrastructure\Persistence\InMemoryProductRepository;
use Lumina\DDD\Infrastructure\Bus\SimpleCommandBus;
use Lumina\DDD\Infrastructure\Bus\SimpleQueryBus;
use Utopia\Http\Http;
use Utopia\Http\Response;

// Create the HTTP instance
$http = Http::getInstance();
$http->setMode(Http::MODE_DEFAULT);

// Set up repository (in-memory for this example)
$productRepository = new InMemoryProductRepository();

// Set up command bus with handlers
$commandBus = new SimpleCommandBus();
$commandBus->registerHandler(
    CreateProductCommand::class,
    new CreateProductCommandHandler($productRepository)
);
$commandBus->registerHandler(
    UpdateProductCommand::class,
    new UpdateProductCommandHandler($productRepository)
);
$commandBus->registerHandler(
    DeleteProductCommand::class,
    new DeleteProductCommandHandler($productRepository)
);

// Set up query bus with handlers
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
    ->action(function ($request, $response) use ($productController) {
        return $productController->index($request, $response);
    });

$http->get('/products/:id')
    ->param('id', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->action(function ($id, $request, $response) use ($productController) {
        return $productController->show($request, $response, $id);
    });

$http->post('/products')
    ->inject('request')
    ->inject('response')
    ->action(function ($request, $response) use ($productController) {
        return $productController->store($request, $response);
    });

$http->put('/products/:id')
    ->param('id', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->action(function ($id, $request, $response) use ($productController) {
        return $productController->update($request, $response, $id);
    });

$http->delete('/products/:id')
    ->param('id', '', 'string', 'Product ID')
    ->inject('request')
    ->inject('response')
    ->action(function ($id, $request, $response) use ($productController) {
        return $productController->destroy($request, $response, $id);
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
