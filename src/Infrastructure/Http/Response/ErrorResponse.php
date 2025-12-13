<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http\Response;

use Throwable;

/**
 * Error response with structured error information.
 */
final class ErrorResponse
{
    /**
     * @param string $message Human-readable error message
     * @param string $code Machine-readable error code
     * @param int $statusCode HTTP status code
     * @param array<string, mixed>|null $details Additional error details
     * @param string|null $traceId Request trace ID for debugging
     */
    public function __construct(
        private readonly string $message,
        private readonly string $code,
        private readonly int $statusCode = 400,
        private readonly ?array $details = null,
        private readonly ?string $traceId = null,
    ) {
    }

    /**
     * Create a bad request error.
     */
    public static function badRequest(string $message, ?array $details = null): self
    {
        return new self($message, 'BAD_REQUEST', 400, $details);
    }

    /**
     * Create an unauthorized error.
     */
    public static function unauthorized(string $message = 'Authentication required'): self
    {
        return new self($message, 'UNAUTHORIZED', 401);
    }

    /**
     * Create a forbidden error.
     */
    public static function forbidden(string $message = 'Access denied'): self
    {
        return new self($message, 'FORBIDDEN', 403);
    }

    /**
     * Create a not found error.
     */
    public static function notFound(string $resource = 'Resource'): self
    {
        return new self("{$resource} not found", 'NOT_FOUND', 404);
    }

    /**
     * Create a method not allowed error.
     */
    public static function methodNotAllowed(string $method): self
    {
        return new self("Method {$method} not allowed", 'METHOD_NOT_ALLOWED', 405);
    }

    /**
     * Create a conflict error.
     */
    public static function conflict(string $message): self
    {
        return new self($message, 'CONFLICT', 409);
    }

    /**
     * Create an unprocessable entity error.
     */
    public static function unprocessableEntity(string $message, ?array $details = null): self
    {
        return new self($message, 'UNPROCESSABLE_ENTITY', 422, $details);
    }

    /**
     * Create a too many requests error.
     */
    public static function tooManyRequests(string $message = 'Too many requests', ?int $retryAfter = null): self
    {
        $details = $retryAfter !== null ? ['retryAfter' => $retryAfter] : null;

        return new self($message, 'TOO_MANY_REQUESTS', 429, $details);
    }

    /**
     * Create an internal server error.
     */
    public static function serverError(string $message = 'An unexpected error occurred'): self
    {
        return new self($message, 'INTERNAL_ERROR', 500);
    }

    /**
     * Create a service unavailable error.
     */
    public static function serviceUnavailable(string $message = 'Service temporarily unavailable'): self
    {
        return new self($message, 'SERVICE_UNAVAILABLE', 503);
    }

    /**
     * Create from an exception.
     */
    public static function fromException(Throwable $exception, bool $includeTrace = false): self
    {
        $details = $includeTrace ? [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ] : null;

        return new self(
            $exception->getMessage(),
            'EXCEPTION',
            500,
            $details,
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * Set the trace ID.
     */
    public function withTraceId(string $traceId): self
    {
        return new self(
            $this->message,
            $this->code,
            $this->statusCode,
            $this->details,
            $traceId,
        );
    }

    /**
     * Convert to array for JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = [
            'success' => false,
            'statusCode' => $this->statusCode,
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
            ],
        ];

        if ($this->details !== null) {
            $response['error']['details'] = $this->details;
        }

        if ($this->traceId !== null) {
            $response['traceId'] = $this->traceId;
        }

        return $response;
    }
}
