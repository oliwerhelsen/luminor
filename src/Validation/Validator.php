<?php

declare(strict_types=1);

namespace Luminor\DDD\Validation;

use Luminor\DDD\Application\Validation\ValidationException;
use Luminor\DDD\Application\Validation\ValidationResult;
use RuntimeException;

/**
 * Validator
 *
 * Validates data against a set of rules.
 */
final class Validator
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, array<string|Rule>> */
    private array $rules;

    /** @var array<string, string> */
    private array $customMessages = [];

    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string|Rule>> $rules
     * @param array<string, string> $customMessages
     */
    public function __construct(
        array $data,
        array $rules,
        array $customMessages = [],
    ) {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }

    /**
     * Validate the data.
     */
    public function validate(): ValidationResult
    {
        $this->errors = [];

        foreach ($this->rules as $attribute => $rules) {
            $value = $this->getValue($attribute);
            $ruleList = is_array($rules) ? $rules : [$rules];

            foreach ($ruleList as $rule) {
                if (! $this->validateRule($attribute, $value, $rule)) {
                    break; // Stop on first error for this attribute
                }
            }
        }

        $isValid = empty($this->errors);

        return new ValidationResult($isValid, $this->errors);
    }

    /**
     * Validate and throw exception if validation fails.
     *
     * @return array<string, mixed> Validated data
     *
     * @throws ValidationException
     */
    public function validated(): array
    {
        $result = $this->validate();

        if (! $result->isValid()) {
            throw new ValidationException('Validation failed', $result->getErrors());
        }

        return $this->data;
    }

    /**
     * Check if validation passes.
     */
    public function passes(): bool
    {
        return $this->validate()->isValid();
    }

    /**
     * Check if validation fails.
     */
    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * Get validation errors.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        if (empty($this->errors)) {
            $this->validate();
        }

        return $this->errors;
    }

    /**
     * Validate a single rule.
     */
    private function validateRule(string $attribute, mixed $value, string|Rule $rule): bool
    {
        if ($rule instanceof Rule) {
            if (! $rule->passes($attribute, $value)) {
                $this->errors[$attribute] = $rule->message();

                return false;
            }

            return true;
        }

        // Parse string rule (e.g., "min:3", "max:10")
        [$ruleName, $parameters] = $this->parseRule($rule);

        // Get the rule validator method
        $method = 'validate' . ucfirst($ruleName);

        if (! method_exists($this, $method)) {
            throw new RuntimeException("Validation rule [{$ruleName}] does not exist.");
        }

        // Call the validation method
        if (! $this->$method($attribute, $value, $parameters)) {
            $message = $this->getMessage($attribute, $ruleName, $parameters);
            $this->errors[$attribute] = $message;

            return false;
        }

        return true;
    }

    /**
     * Parse a rule string.
     *
     * @return array{string, array<string>}
     */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $params] = explode(':', $rule, 2);
            $parameters = explode(',', $params);

            return [$name, $parameters];
        }

        return [$rule, []];
    }

    /**
     * Get value from data using dot notation.
     */
    private function getValue(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Get error message.
     *
     * @param array<string> $parameters
     */
    private function getMessage(string $attribute, string $rule, array $parameters): string
    {
        $key = "{$attribute}.{$rule}";

        if (isset($this->customMessages[$key])) {
            return $this->customMessages[$key];
        }

        return $this->getDefaultMessage($attribute, $rule, $parameters);
    }

    /**
     * Get default error message.
     *
     * @param array<string> $parameters
     */
    private function getDefaultMessage(string $attribute, string $rule, array $parameters): string
    {
        $messages = [
            'required' => "The {$attribute} field is required.",
            'email' => "The {$attribute} must be a valid email address.",
            'numeric' => "The {$attribute} must be a number.",
            'integer' => "The {$attribute} must be an integer.",
            'string' => "The {$attribute} must be a string.",
            'boolean' => "The {$attribute} must be true or false.",
            'array' => "The {$attribute} must be an array.",
            'min' => "The {$attribute} must be at least {$parameters[0]}.",
            'max' => "The {$attribute} must not be greater than {$parameters[0]}.",
            'between' => "The {$attribute} must be between {$parameters[0]} and {$parameters[1]}.",
            'in' => "The selected {$attribute} is invalid.",
            'regex' => "The {$attribute} format is invalid.",
            'url' => "The {$attribute} must be a valid URL.",
            'alpha' => "The {$attribute} may only contain letters.",
            'alphaNumeric' => "The {$attribute} may only contain letters and numbers.",
            'confirmed' => "The {$attribute} confirmation does not match.",
            'same' => "The {$attribute} and {$parameters[0]} must match.",
            'different' => "The {$attribute} and {$parameters[0]} must be different.",
        ];

        return $messages[$rule] ?? "The {$attribute} is invalid.";
    }

    // Validation Methods

    /**
     * @param array<string> $parameters
     */
    protected function validateRequired(string $attribute, mixed $value, array $parameters): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateEmail(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateNumeric(string $attribute, mixed $value, array $parameters): bool
    {
        return is_numeric($value);
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateInteger(string $attribute, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateString(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value);
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateBoolean(string $attribute, mixed $value, array $parameters): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateArray(string $attribute, mixed $value, array $parameters): bool
    {
        return is_array($value);
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateMin(string $attribute, mixed $value, array $parameters): bool
    {
        $min = (float) $parameters[0];

        if (is_numeric($value)) {
            return (float) $value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateMax(string $attribute, mixed $value, array $parameters): bool
    {
        $max = (float) $parameters[0];

        if (is_numeric($value)) {
            return (float) $value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateBetween(string $attribute, mixed $value, array $parameters): bool
    {
        $min = (float) $parameters[0];
        $max = (float) $parameters[1];

        if (is_numeric($value)) {
            $num = (float) $value;

            return $num >= $min && $num <= $max;
        }

        if (is_string($value)) {
            $length = mb_strlen($value);

            return $length >= $min && $length <= $max;
        }

        if (is_array($value)) {
            $count = count($value);

            return $count >= $min && $count <= $max;
        }

        return false;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateIn(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, $parameters, true);
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateRegex(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match($parameters[0], $value) === 1;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateUrl(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateAlpha(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateAlphaNumeric(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateConfirmed(string $attribute, mixed $value, array $parameters): bool
    {
        $confirmationField = $attribute . '_confirmation';
        $confirmationValue = $this->getValue($confirmationField);

        return $value === $confirmationValue;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateSame(string $attribute, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);

        return $value === $other;
    }

    /**
     * @param array<string> $parameters
     */
    protected function validateDifferent(string $attribute, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);

        return $value !== $other;
    }
}
