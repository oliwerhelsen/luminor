<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http\Response;

use Luminor\DDD\Application\DTO\DataTransferObject;
use Luminor\DDD\Application\DTO\PagedResult;
use Utopia\Http\Response;

/**
 * Standardized API response format.
 *
 * Provides consistent JSON response structure for API endpoints.
 */
final class ApiResponse
{
    private const DEFAULT_SUCCESS_CODE = 200;

    private const DEFAULT_CREATED_CODE = 201;

    private const DEFAULT_NO_CONTENT_CODE = 204;

    /**
     * Create a success response with data.
     *
     * @param mixed $data The response data
     * @param int $statusCode HTTP status code
     *
     * @return array<string, mixed>
     */
    public static function success(mixed $data = null, int $statusCode = self::DEFAULT_SUCCESS_CODE): array
    {
        $response = [
            'success' => true,
            'statusCode' => $statusCode,
        ];

        if ($data !== null) {
            $response['data'] = self::normalizeData($data);
        }

        return $response;
    }

    /**
     * Create a success response for created resources.
     *
     * @param mixed $data The created resource data
     *
     * @return array<string, mixed>
     */
    public static function created(mixed $data = null): array
    {
        return self::success($data, self::DEFAULT_CREATED_CODE);
    }

    /**
     * Create a no content response.
     *
     * @return array<string, mixed>
     */
    public static function noContent(): array
    {
        return [
            'success' => true,
            'statusCode' => self::DEFAULT_NO_CONTENT_CODE,
        ];
    }

    /**
     * Create a paginated response.
     *
     * @param PagedResult<mixed> $pagedResult
     *
     * @return array<string, mixed>
     */
    public static function paginated(PagedResult $pagedResult): array
    {
        return [
            'success' => true,
            'statusCode' => self::DEFAULT_SUCCESS_CODE,
            'data' => array_map(
                fn ($item) => self::normalizeData($item),
                $pagedResult->getItems(),
            ),
            'pagination' => [
                'page' => $pagedResult->getPage(),
                'perPage' => $pagedResult->getPerPage(),
                'totalCount' => $pagedResult->getTotalCount(),
                'totalPages' => $pagedResult->getTotalPages(),
                'hasNextPage' => $pagedResult->hasNextPage(),
                'hasPreviousPage' => $pagedResult->hasPreviousPage(),
            ],
        ];
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message
     * @param string $code Error code
     * @param int $statusCode HTTP status code
     * @param array<string, mixed>|null $details Additional error details
     *
     * @return array<string, mixed>
     */
    public static function error(
        string $message,
        string $code = 'ERROR',
        int $statusCode = 400,
        ?array $details = null,
    ): array {
        $response = [
            'success' => false,
            'statusCode' => $statusCode,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        return $response;
    }

    /**
     * Create a not found error response.
     *
     * @param string $message Error message
     *
     * @return array<string, mixed>
     */
    public static function notFound(string $message = 'Resource not found'): array
    {
        return self::error($message, 'NOT_FOUND', 404);
    }

    /**
     * Create an unauthorized error response.
     *
     * @param string $message Error message
     *
     * @return array<string, mixed>
     */
    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return self::error($message, 'UNAUTHORIZED', 401);
    }

    /**
     * Create a forbidden error response.
     *
     * @param string $message Error message
     *
     * @return array<string, mixed>
     */
    public static function forbidden(string $message = 'Forbidden'): array
    {
        return self::error($message, 'FORBIDDEN', 403);
    }

    /**
     * Create an internal server error response.
     *
     * @param string $message Error message
     *
     * @return array<string, mixed>
     */
    public static function serverError(string $message = 'Internal server error'): array
    {
        return self::error($message, 'SERVER_ERROR', 500);
    }

    /**
     * Normalize data for JSON response.
     */
    private static function normalizeData(mixed $data): mixed
    {
        if ($data instanceof DataTransferObject) {
            return $data->toArray();
        }

        if ($data instanceof PagedResult) {
            return $data->toArray();
        }

        if (is_array($data)) {
            return array_map(fn ($item) => self::normalizeData($item), $data);
        }

        return $data;
    }
}
