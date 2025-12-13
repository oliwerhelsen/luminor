<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\Validation;

use Exception;

/**
 * Exception thrown when validation fails.
 */
final class ValidationException extends Exception
{
    public function __construct(
        private readonly ValidationResult $result,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message);
    }

    /**
     * Create from a validation result.
     */
    public static function fromResult(ValidationResult $result): self
    {
        $message = $result->getFirstError() ?? 'Validation failed';

        return new self($result, $message);
    }

    /**
     * Create with a single field error.
     */
    public static function withError(string $field, string $message): self
    {
        return new self(ValidationResult::withError($field, $message), $message);
    }

    /**
     * Get the validation result.
     */
    public function getValidationResult(): ValidationResult
    {
        return $this->result;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->result->getErrors();
    }
}
