<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain\Abstractions;

use Exception;
use Throwable;

/**
 * Base class for domain-specific exceptions.
 *
 * Domain exceptions represent business rule violations or invalid operations
 * within the domain. They carry additional context that can be used for
 * logging, debugging, or presenting errors to users.
 */
class DomainException extends Exception
{
    /**
     * @param string $message The exception message
     * @param string $code A domain-specific error code
     * @param array<string, mixed> $context Additional context for the exception
     * @param Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = 'DOMAIN_ERROR',
        private readonly array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the domain-specific error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get additional context for the exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert the exception to an array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errorCode' => $this->errorCode,
            'context' => $this->context,
        ];
    }

    /**
     * Create a new domain exception with a specific error code.
     *
     * @param array<string, mixed> $context
     */
    public static function withCode(string $message, string $code, array $context = []): static
    {
        return new static($message, $code, $context);
    }
}
