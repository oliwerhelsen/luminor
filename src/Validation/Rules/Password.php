<?php

declare(strict_types=1);

namespace Lumina\DDD\Validation\Rules;

use Lumina\DDD\Validation\Rule;

/**
 * Password Rule
 *
 * Validates password strength requirements.
 */
final class Password implements Rule
{
    private int $minLength = 8;
    private bool $requireUppercase = true;
    private bool $requireLowercase = true;
    private bool $requireNumbers = true;
    private bool $requireSpecialChars = false;

    public function minLength(int $length): self
    {
        $this->minLength = $length;
        return $this;
    }

    public function requireUppercase(bool $require = true): self
    {
        $this->requireUppercase = $require;
        return $this;
    }

    public function requireLowercase(bool $require = true): self
    {
        $this->requireLowercase = $require;
        return $this;
    }

    public function requireNumbers(bool $require = true): self
    {
        $this->requireNumbers = $require;
        return $this;
    }

    public function requireSpecialChars(bool $require = true): self
    {
        $this->requireSpecialChars = $require;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        if (mb_strlen($value) < $this->minLength) {
            return false;
        }

        if ($this->requireUppercase && !preg_match('/[A-Z]/', $value)) {
            return false;
        }

        if ($this->requireLowercase && !preg_match('/[a-z]/', $value)) {
            return false;
        }

        if ($this->requireNumbers && !preg_match('/[0-9]/', $value)) {
            return false;
        }

        if ($this->requireSpecialChars && !preg_match('/[^a-zA-Z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function message(): string
    {
        $requirements = [];

        $requirements[] = "at least {$this->minLength} characters";

        if ($this->requireUppercase) {
            $requirements[] = "one uppercase letter";
        }

        if ($this->requireLowercase) {
            $requirements[] = "one lowercase letter";
        }

        if ($this->requireNumbers) {
            $requirements[] = "one number";
        }

        if ($this->requireSpecialChars) {
            $requirements[] = "one special character";
        }

        return "The password must contain " . implode(', ', $requirements) . ".";
    }
}
