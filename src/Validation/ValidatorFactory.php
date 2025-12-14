<?php

declare(strict_types=1);

namespace Luminor\Validation;

/**
 * Validator Factory
 *
 * Creates validator instances.
 */
final class ValidatorFactory
{
    /**
     * Create a new validator instance.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<string|Rule>> $rules
     * @param array<string, string> $customMessages
     */
    public function make(
        array $data,
        array $rules,
        array $customMessages = []
    ): Validator {
        return new Validator($data, $rules, $customMessages);
    }
}
