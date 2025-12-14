<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http;

use Throwable;
use Luminor\DDD\Application\Validation\ValidationException;
use Luminor\DDD\Domain\Abstractions\DomainException;
use Luminor\DDD\Domain\Repository\AggregateNotFoundException;
use Luminor\DDD\Infrastructure\Http\Response\ApiResponse;
use Luminor\DDD\Infrastructure\Http\Response\ErrorResponse;
use Luminor\DDD\Infrastructure\Http\Response\ValidationErrorResponse;
use Luminor\DDD\Http\Response;

/**
 * Global exception handler for API endpoints.
 *
 * Converts exceptions into standardized API error responses.
 */
final class ExceptionHandler
{
    /**
     * Whether to include stack traces in error responses.
     */
    private bool $debug = false;

    /**
     * Custom exception handlers.
     *
     * @var array<string, callable(Throwable, Response): void>
     */
    private array $customHandlers = [];

    /**
     * Error logger callback.
     *
     * @var callable(Throwable): void|null
     */
    private $errorLogger = null;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Create a handler for development environment.
     */
    public static function forDevelopment(): self
    {
        return new self(debug: true);
    }

    /**
     * Create a handler for production environment.
     */
    public static function forProduction(): self
    {
        return new self(debug: false);
    }

    /**
     * Enable or disable debug mode.
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Set an error logger callback.
     *
     * @param callable(Throwable): void $logger
     */
    public function setErrorLogger(callable $logger): self
    {
        $this->errorLogger = $logger;
        return $this;
    }

    /**
     * Register a custom exception handler.
     *
     * @param string $exceptionClass The fully qualified exception class name
     * @param callable(Throwable, Response): void $handler The handler callback
     */
    public function registerHandler(string $exceptionClass, callable $handler): self
    {
        $this->customHandlers[$exceptionClass] = $handler;
        return $this;
    }

    /**
     * Handle an exception and send an appropriate response.
     */
    public function handle(Throwable $exception, Response $response): void
    {
        // Log the error if a logger is configured
        if ($this->errorLogger !== null) {
            ($this->errorLogger)($exception);
        }

        // Check for custom handlers
        foreach ($this->customHandlers as $exceptionClass => $handler) {
            if ($exception instanceof $exceptionClass) {
                $handler($exception, $response);
                return;
            }
        }

        // Handle known exception types
        match (true) {
            $exception instanceof ValidationException => $this->handleValidationException($exception, $response),
            $exception instanceof AggregateNotFoundException => $this->handleNotFoundException($exception, $response),
            $exception instanceof DomainException => $this->handleDomainException($exception, $response),
            $exception instanceof \InvalidArgumentException => $this->handleBadRequest($exception, $response),
            $exception instanceof \RuntimeException => $this->handleRuntimeException($exception, $response),
            default => $this->handleGenericException($exception, $response),
        };
    }

    /**
     * Handle validation exceptions.
     */
    private function handleValidationException(ValidationException $exception, Response $response): void
    {
        $errorResponse = ValidationErrorResponse::fromValidationResult($exception->getValidationResult());
        $response->setStatusCode(422);
        $response->json($errorResponse->toArray());
    }

    /**
     * Handle not found exceptions.
     */
    private function handleNotFoundException(AggregateNotFoundException $exception, Response $response): void
    {
        $error = ErrorResponse::notFound($exception->getMessage());
        $response->setStatusCode(404);
        $response->json($error->toArray());
    }

    /**
     * Handle domain exceptions.
     */
    private function handleDomainException(DomainException $exception, Response $response): void
    {
        $error = ErrorResponse::unprocessableEntity(
            $exception->getMessage(),
            $this->debug ? $this->getExceptionDetails($exception) : null
        );
        $response->setStatusCode(422);
        $response->json($error->toArray());
    }

    /**
     * Handle bad request exceptions.
     */
    private function handleBadRequest(\InvalidArgumentException $exception, Response $response): void
    {
        $error = ErrorResponse::badRequest(
            $exception->getMessage(),
            $this->debug ? $this->getExceptionDetails($exception) : null
        );
        $response->setStatusCode(400);
        $response->json($error->toArray());
    }

    /**
     * Handle runtime exceptions.
     */
    private function handleRuntimeException(\RuntimeException $exception, Response $response): void
    {
        // Check if it's a "safe" runtime exception that can show its message
        $message = $this->debug
            ? $exception->getMessage()
            : 'An error occurred while processing your request';

        $error = ErrorResponse::serverError($message);
        $response->setStatusCode(500);
        $response->json($this->addDebugInfo($error->toArray(), $exception));
    }

    /**
     * Handle generic/unknown exceptions.
     */
    private function handleGenericException(Throwable $exception, Response $response): void
    {
        $message = $this->debug
            ? $exception->getMessage()
            : 'An unexpected error occurred';

        $error = ErrorResponse::serverError($message);
        $response->setStatusCode(500);
        $response->json($this->addDebugInfo($error->toArray(), $exception));
    }

    /**
     * Get exception details for debug mode.
     *
     * @return array<string, mixed>
     */
    private function getExceptionDetails(Throwable $exception): array
    {
        return [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    /**
     * Add debug information to error response.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function addDebugInfo(array $response, Throwable $exception): array
    {
        if (!$this->debug) {
            return $response;
        }

        $response['debug'] = [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception),
        ];

        return $response;
    }

    /**
     * Format the exception trace for readability.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatTrace(Throwable $exception): array
    {
        $trace = [];
        foreach ($exception->getTrace() as $frame) {
            $trace[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }

        return array_slice($trace, 0, 10); // Limit trace depth
    }

    /**
     * Create a response array from an exception.
     *
     * Useful for testing or manual response creation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof ValidationException => ValidationErrorResponse::fromValidationResult(
                $exception->getValidationResult()
            )->toArray(),
            $exception instanceof AggregateNotFoundException => ErrorResponse::notFound(
                $exception->getMessage()
            )->toArray(),
            $exception instanceof DomainException => ErrorResponse::unprocessableEntity(
                $exception->getMessage(),
                $this->debug ? $this->getExceptionDetails($exception) : null
            )->toArray(),
            $exception instanceof \InvalidArgumentException => ErrorResponse::badRequest(
                $exception->getMessage(),
                $this->debug ? $this->getExceptionDetails($exception) : null
            )->toArray(),
            default => $this->addDebugInfo(
                ErrorResponse::serverError(
                    $this->debug ? $exception->getMessage() : 'An unexpected error occurred'
                )->toArray(),
                $exception
            ),
        };
    }

    /**
     * Get the HTTP status code for an exception.
     */
    public function getStatusCode(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AggregateNotFoundException => 404,
            $exception instanceof DomainException => 422,
            $exception instanceof \InvalidArgumentException => 400,
            default => 500,
        };
    }
}
