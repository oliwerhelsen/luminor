<?php

declare(strict_types=1);

namespace Example\BasicApi\Infrastructure\Http\Controllers;

use Example\BasicApi\Application\Commands\CreateProductCommand;
use Example\BasicApi\Application\Commands\DeleteProductCommand;
use Example\BasicApi\Application\Commands\UpdateProductCommand;
use Example\BasicApi\Application\Queries\GetProductQuery;
use Example\BasicApi\Application\Queries\ListProductsQuery;
use Luminor\DDD\Application\Bus\CommandBusInterface;
use Luminor\DDD\Application\Bus\QueryBusInterface;
use Luminor\DDD\Infrastructure\Http\ApiController;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

/**
 * Product API Controller demonstrating CQRS pattern usage.
 */
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

        // Basic validation
        $errors = $this->validateStore($data);
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

    /**
     * DELETE /products/:id
     */
    public function destroy(Request $request, Response $response, string $id): Response
    {
        $success = $this->commandBus->dispatch(new DeleteProductCommand($id));

        if (!$success) {
            return $this->notFound($response, 'Product not found');
        }

        return $this->noContent($response);
    }

    /**
     * Validate store request data.
     */
    private function validateStore(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = ['Name is required'];
        }

        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors['price'] = ['Price is required and must be a number'];
        } elseif ($data['price'] < 0) {
            $errors['price'] = ['Price cannot be negative'];
        }

        return $errors;
    }
}
