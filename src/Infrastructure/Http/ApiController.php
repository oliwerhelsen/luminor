<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http;

use Luminor\DDD\Application\Bus\CommandBusInterface;
use Luminor\DDD\Application\Bus\QueryBusInterface;
use Luminor\DDD\Application\CQRS\Command;
use Luminor\DDD\Application\CQRS\Query;
use Luminor\DDD\Application\DTO\PagedResult;
use Luminor\DDD\Infrastructure\Http\Response\ApiResponse;
use Luminor\DDD\Infrastructure\Http\Response\ErrorResponse;
use Luminor\DDD\Infrastructure\Http\Response\ValidationErrorResponse;
use Utopia\Http\Request;
use Utopia\Http\Response;

/**
 * Base API controller with common helpers.
 *
 * Provides utilities for handling requests, dispatching commands/queries,
 * and formatting responses.
 */
abstract class ApiController
{
    protected ?CommandBusInterface $commandBus = null;
    protected ?QueryBusInterface $queryBus = null;

    /**
     * Set the command bus for dispatching commands.
     */
    public function setCommandBus(CommandBusInterface $commandBus): void
    {
        $this->commandBus = $commandBus;
    }

    /**
     * Set the query bus for dispatching queries.
     */
    public function setQueryBus(QueryBusInterface $queryBus): void
    {
        $this->queryBus = $queryBus;
    }

    /**
     * Dispatch a command and return the result.
     */
    protected function dispatchCommand(Command $command): mixed
    {
        if ($this->commandBus === null) {
            throw new \RuntimeException('Command bus not configured');
        }

        return $this->commandBus->dispatch($command);
    }

    /**
     * Dispatch a query and return the result.
     */
    protected function dispatchQuery(Query $query): mixed
    {
        if ($this->queryBus === null) {
            throw new \RuntimeException('Query bus not configured');
        }

        return $this->queryBus->dispatch($query);
    }

    /**
     * Get a required parameter from the request.
     *
     * @throws \InvalidArgumentException If the parameter is missing
     */
    protected function getRequiredParam(Request $request, string $name): mixed
    {
        $value = $request->getParam($name);

        if ($value === null || $value === '') {
            throw new \InvalidArgumentException("Missing required parameter: {$name}");
        }

        return $value;
    }

    /**
     * Get an optional parameter from the request with a default value.
     */
    protected function getOptionalParam(Request $request, string $name, mixed $default = null): mixed
    {
        $value = $request->getParam($name);
        return $value !== null && $value !== '' ? $value : $default;
    }

    /**
     * Get pagination parameters from the request.
     *
     * @return array{page: int, perPage: int}
     */
    protected function getPaginationParams(Request $request, int $defaultPerPage = 25, int $maxPerPage = 100): array
    {
        $page = max(1, (int) $this->getOptionalParam($request, 'page', 1));
        $perPage = min($maxPerPage, max(1, (int) $this->getOptionalParam($request, 'perPage', $defaultPerPage)));

        return [
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Get sorting parameters from the request.
     *
     * @param array<int, string> $allowedFields Fields that can be sorted
     * @return array{field: string|null, direction: string}
     */
    protected function getSortingParams(Request $request, array $allowedFields = [], string $defaultField = null): array
    {
        $field = $this->getOptionalParam($request, 'sortBy', $defaultField);
        $direction = strtoupper($this->getOptionalParam($request, 'sortDir', 'ASC'));

        // Validate field
        if ($field !== null && count($allowedFields) > 0 && !in_array($field, $allowedFields, true)) {
            $field = $defaultField;
        }

        // Validate direction
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        return [
            'field' => $field,
            'direction' => $direction,
        ];
    }

    /**
     * Parse JSON body from request.
     *
     * @return array<string, mixed>
     */
    protected function parseJsonBody(Request $request): array
    {
        $body = $request->getPayload();

        if (empty($body)) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON body: ' . $e->getMessage());
        }
    }

    /**
     * Send a success response with data.
     */
    protected function respondSuccess(Response $response, mixed $data = null, int $statusCode = 200): void
    {
        $response->setStatusCode($statusCode);
        $response->json(ApiResponse::success($data, $statusCode));
    }

    /**
     * Send a created response.
     */
    protected function respondCreated(Response $response, mixed $data = null): void
    {
        $response->setStatusCode(201);
        $response->json(ApiResponse::created($data));
    }

    /**
     * Send a no content response.
     */
    protected function respondNoContent(Response $response): void
    {
        $response->setStatusCode(204);
        $response->noContent();
    }

    /**
     * Send a paginated response.
     *
     * @param PagedResult<mixed> $pagedResult
     */
    protected function respondPaginated(Response $response, PagedResult $pagedResult): void
    {
        $response->setStatusCode(200);
        $response->json(ApiResponse::paginated($pagedResult));
    }

    /**
     * Send an error response.
     */
    protected function respondError(
        Response $response,
        string $message,
        string $code = 'ERROR',
        int $statusCode = 400,
        ?array $details = null
    ): void {
        $response->setStatusCode($statusCode);
        $response->json(ApiResponse::error($message, $code, $statusCode, $details));
    }

    /**
     * Send an error response from an ErrorResponse object.
     */
    protected function respondWithError(Response $response, ErrorResponse $error): void
    {
        $response->setStatusCode($error->getStatusCode());
        $response->json($error->toArray());
    }

    /**
     * Send a validation error response.
     */
    protected function respondValidationError(Response $response, ValidationErrorResponse $error): void
    {
        $response->setStatusCode(422);
        $response->json($error->toArray());
    }

    /**
     * Send a not found response.
     */
    protected function respondNotFound(Response $response, string $message = 'Resource not found'): void
    {
        $response->setStatusCode(404);
        $response->json(ApiResponse::notFound($message));
    }

    /**
     * Send an unauthorized response.
     */
    protected function respondUnauthorized(Response $response, string $message = 'Unauthorized'): void
    {
        $response->setStatusCode(401);
        $response->json(ApiResponse::unauthorized($message));
    }

    /**
     * Send a forbidden response.
     */
    protected function respondForbidden(Response $response, string $message = 'Forbidden'): void
    {
        $response->setStatusCode(403);
        $response->json(ApiResponse::forbidden($message));
    }
}
