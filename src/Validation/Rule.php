<?php

declare(strict_types=1);

namespace Luminor\DDD\Validation;

/**
 * Validation Rule Interface
 *
 * Defines a contract for validation rules.
 */
interface Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute The attribute name
     * @param mixed $value The value to validate
     * @return bool True if validation passes
     */
    public function passes(string $attribute, mixed $value): bool;

    /**
     * Get the validation error message.
     *
     * @return string The error message
     */
    public function message(): string;
}
