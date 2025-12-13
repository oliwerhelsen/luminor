<?php

declare(strict_types=1);

namespace Luminor\DDD\Support;

use RuntimeException;

/**
 * Environment variable helper for retrieving and type-casting environment values.
 *
 * Supports retrieval from $_ENV, $_SERVER, and getenv() with automatic
 * type casting for common values like booleans, null, and empty strings.
 */
final class Env
{
    /**
     * Indicates if the putenv adapter is enabled.
     */
    protected static bool $putenv = true;

    /**
     * The environment repository instance.
     */
    protected static ?object $repository = null;

    /**
     * Get the value of an environment variable.
     *
     * @param string $key The environment variable name
     * @param mixed $default Default value if not found
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getEnvironmentVariable($key, $default);
    }

    /**
     * Get the value of a required environment variable.
     *
     * @param string $key The environment variable name
     *
     * @throws RuntimeException If the environment variable is not set
     */
    public static function getOrFail(string $key): mixed
    {
        $value = self::getEnvironmentVariable($key, null);

        if ($value === null) {
            throw new RuntimeException("Environment variable [{$key}] is not set.");
        }

        return $value;
    }

    /**
     * Retrieve the environment variable value.
     *
     * @param string $key The environment variable name
     * @param mixed $default Default value if not found
     */
    protected static function getEnvironmentVariable(string $key, mixed $default): mixed
    {
        // If we have a repository (phpdotenv), use it
        if (self::$repository !== null && method_exists(self::$repository, 'get')) {
            $value = self::$repository->get($key);

            if ($value !== null) {
                return self::transformValue($value);
            }
        }

        // Try $_ENV first
        if (isset($_ENV[$key])) {
            return self::transformValue($_ENV[$key]);
        }

        // Try $_SERVER second
        if (isset($_SERVER[$key])) {
            return self::transformValue($_SERVER[$key]);
        }

        // Try getenv() as fallback
        $value = getenv($key);

        if ($value !== false) {
            return self::transformValue($value);
        }

        // Return default value (resolve if callable)
        return value($default);
    }

    /**
     * Transform the value based on common conventions.
     *
     * @param mixed $value The raw value
     *
     * @return mixed The transformed value
     */
    protected static function transformValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Handle special string values
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => self::stripQuotes($value),
        };
    }

    /**
     * Strip quotes from a string value.
     *
     * @param string $value The value to strip
     *
     * @return string The unquoted value
     */
    protected static function stripQuotes(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    /**
     * Set the environment repository instance.
     *
     * This is used when vlucas/phpdotenv is available.
     *
     * @param object|null $repository The repository instance
     */
    public static function setRepository(?object $repository): void
    {
        self::$repository = $repository;
    }

    /**
     * Get the environment repository instance.
     */
    public static function getRepository(): ?object
    {
        return self::$repository;
    }

    /**
     * Enable or disable putenv adapter.
     *
     * @param bool $enabled Whether putenv should be enabled
     */
    public static function enablePutenv(bool $enabled = true): void
    {
        self::$putenv = $enabled;
    }

    /**
     * Check if putenv adapter is enabled.
     */
    public static function putenvEnabled(): bool
    {
        return self::$putenv;
    }
}
