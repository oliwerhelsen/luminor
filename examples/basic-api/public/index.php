<?php

declare(strict_types=1);

/**
 * Basic API Example - Entry Point
 *
 * This demonstrates a simple REST API using the Luminor DDD Framework.
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
use Example\BasicApi\Infrastructure\Http\Controllers\ProductController;
use Example\BasicApi\Infrastructure\Persistence\InMemoryProductRepository;
use Luminor\DDD\Infrastructure\Bus\SimpleCommandBus;
use Luminor\DDD\Infrastructure\Bus\SimpleQueryBus;
use Luminor\DDD\Http\HttpKernel;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

// Create the HTTP kernel
$http = HttpKernel::getInstance();

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
$http->get('/products', function (Request $request, Response $response) use ($productController) {
    $productController->index($request, $response);
});

$http->get('/products/:id', function (Request $request, Response $response) use ($productController) {
    $productController->show($request, $response);
});

$http->post('/products', function (Request $request, Response $response) use ($productController) {
    $productController->store($request, $response);
});

$http->put('/products/:id', function (Request $request, Response $response) use ($productController) {
    $productController->update($request, $response);
});

$http->delete('/products/:id', function (Request $request, Response $response) use ($productController) {
    $productController->destroy($request, $response);
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
