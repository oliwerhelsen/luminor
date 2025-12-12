<?php

declare(strict_types=1);

namespace Lumina\DDD\Application\Validation;

/**
 * Result of a validation operation.
 *
 * Contains the validation status and any error messages.
 */
final class ValidationResult
{
    /**
     * @param array<string, array<int, string>> $errors Field => error messages mapping
     */
    private function __construct(
        private readonly bool $valid,
        private readonly array $errors
    ) {
    }

    /**
     * Create a successful validation result.
     */
    public static function valid(): self
    {
        return new self(true, []);
    }

    /**
     * Create a failed validation result with errors.
     *
     * @param array<string, array<int, string>> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }

    /**
     * Create a failed validation result with a single error.
     */
    public static function withError(string $field, string $message): self
    {
        return new self(false, [$field => [$message]]);
    }

    /**
     * Check if the validation passed.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Check if the validation failed.
     */
    public function isInvalid(): bool
    {
        return !$this->valid;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
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
     * Check if a specific field has errors.
     */
    public function hasErrorsFor(string $field): bool
    {
        return isset($this->errors[$field]) && count($this->errors[$field]) > 0;
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array<int, string>
     */
    public function getAllMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $messages[] = $message;
            }
        }
        return $messages;
    }

    /**
     * Get the first error message.
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (count($fieldErrors) > 0) {
                return $fieldErrors[0];
            }
        }
        return null;
    }

    /**
     * Merge another validation result into this one.
     */
    public function merge(ValidationResult $other): self
    {
        if ($this->valid && $other->valid) {
            return self::valid();
        }

        $mergedErrors = $this->errors;
        foreach ($other->errors as $field => $messages) {
            if (!isset($mergedErrors[$field])) {
                $mergedErrors[$field] = [];
            }
            $mergedErrors[$field] = array_merge($mergedErrors[$field], $messages);
        }

        return self::invalid($mergedErrors);
    }

    /**
     * Add an error to this result.
     */
    public function addError(string $field, string $message): self
    {
        $errors = $this->errors;
        if (!isset($errors[$field])) {
            $errors[$field] = [];
        }
        $errors[$field][] = $message;

        return self::invalid($errors);
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
        ];
    }
}
