<?php

declare(strict_types=1);

namespace Luminor\Application\Validation;

/**
 * Collection of common validation rules.
 *
 * These rules can be used in custom validators to build validation logic.
 */
final class Rules
{
    /**
     * Check if a value is not empty.
     */
    public static function required(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        return true;
    }

    /**
     * Check if a string meets minimum length requirement.
     */
    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen($value) >= $min;
    }

    /**
     * Check if a string doesn't exceed maximum length.
     */
    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }

    /**
     * Check if a string length is within a range.
     */
    public static function lengthBetween(string $value, int $min, int $max): bool
    {
        $length = mb_strlen($value);
        return $length >= $min && $length <= $max;
    }

    /**
     * Check if a value is a valid email address.
     */
    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if a value is a valid URL.
     */
    public static function url(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if a value matches a regex pattern.
     */
    public static function pattern(string $value, string $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Check if a numeric value is at least a minimum.
     */
    public static function min(int|float $value, int|float $min): bool
    {
        return $value >= $min;
    }

    /**
     * Check if a numeric value doesn't exceed a maximum.
     */
    public static function max(int|float $value, int|float $max): bool
    {
        return $value <= $max;
    }

    /**
     * Check if a numeric value is within a range.
     */
    public static function between(int|float $value, int|float $min, int|float $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * Check if a value is in a list of allowed values.
     *
     * @param array<int, mixed> $allowed
     */
    public static function in(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /**
     * Check if a value is not in a list of disallowed values.
     *
     * @param array<int, mixed> $disallowed
     */
    public static function notIn(mixed $value, array $disallowed): bool
    {
        return !in_array($value, $disallowed, true);
    }

    /**
     * Check if a string contains only alphanumeric characters.
     */
    public static function alphanumeric(string $value): bool
    {
        return ctype_alnum($value);
    }

    /**
     * Check if a string contains only alphabetic characters.
     */
    public static function alpha(string $value): bool
    {
        return ctype_alpha($value);
    }

    /**
     * Check if a value is numeric.
     */
    public static function numeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    /**
     * Check if a value is an integer.
     */
    public static function integer(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }

    /**
     * Check if a value is a valid UUID.
     */
    public static function uuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }

    /**
     * Check if a value is a valid date string.
     */
    public static function date(string $value, string $format = 'Y-m-d'): bool
    {
        $date = \DateTimeImmutable::createFromFormat($format, $value);
        return $date !== false && $date->format($format) === $value;
    }

    /**
     * Check if a value is a valid datetime string.
     */
    public static function datetime(string $value, string $format = 'Y-m-d H:i:s'): bool
    {
        return self::date($value, $format);
    }

    /**
     * Check if an array has a minimum number of items.
     *
     * @param array<mixed> $value
     */
    public static function arrayMinCount(array $value, int $min): bool
    {
        return count($value) >= $min;
    }

    /**
     * Check if an array doesn't exceed a maximum number of items.
     *
     * @param array<mixed> $value
     */
    public static function arrayMaxCount(array $value, int $max): bool
    {
        return count($value) <= $max;
    }

    /**
     * Check if two values are equal.
     */
    public static function equals(mixed $value, mixed $expected): bool
    {
        return $value === $expected;
    }

    /**
     * Check if a value is different from another.
     */
    public static function different(mixed $value, mixed $other): bool
    {
        return $value !== $other;
    }
}
