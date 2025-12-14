<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Http\Response;

use Luminor\Application\Validation\ValidationResult;

/**
 * Specialized error response for validation errors.
 */
final class ValidationErrorResponse
{
    /**
     * @param array<string, array<int, string>> $errors Field => error messages
     * @param string $message Overall error message
     */
    public function __construct(
        private readonly array $errors,
        private readonly string $message = 'Validation failed'
    ) {
    }

    /**
     * Create from a ValidationResult.
     */
    public static function fromValidationResult(ValidationResult $result): self
    {
        return new self(
            $result->getErrors(),
            $result->getFirstError() ?? 'Validation failed'
        );
    }

    /**
     * Create with a single field error.
     */
    public static function withError(string $field, string $message): self
    {
        return new self([$field => [$message]], $message);
    }

    /**
     * Create with multiple field errors.
     *
     * @param array<string, string|array<int, string>> $errors
     */
    public static function withErrors(array $errors): self
    {
        $normalized = [];
        foreach ($errors as $field => $messages) {
            $normalized[$field] = is_array($messages) ? $messages : [$messages];
        }

        return new self($normalized);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return 422;
    }

    /**
     * Check if a specific field has errors.
     */
    public function hasErrorsFor(string $field): bool
    {
        return isset($this->errors[$field]) && count($this->errors[$field]) > 0;
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<int, string>
     */
    public function getErrorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Convert to array for JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'statusCode' => 422,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $this->message,
                'errors' => $this->errors,
            ],
        ];
    }
}
