<?php

declare(strict_types=1);

namespace Luminor\DDD\Module;

use RuntimeException;

/**
 * Exception for module-related errors.
 */
final class ModuleException extends RuntimeException
{
    /**
     * Module is already registered.
     */
    public static function alreadyRegistered(string $name): self
    {
        return new self(sprintf('Module "%s" is already registered', $name));
    }

    /**
     * Module not found.
     */
    public static function notFound(string $name): self
    {
        return new self(sprintf('Module "%s" not found', $name));
    }

    /**
     * Circular dependency detected.
     */
    public static function circularDependency(string $name): self
    {
        return new self(sprintf('Circular dependency detected for module "%s"', $name));
    }

    /**
     * Required dependency not found.
     */
    public static function dependencyNotFound(string $module, string $dependency): self
    {
        return new self(
            sprintf('Module "%s" requires "%s" which is not registered', $module, $dependency),
        );
    }

    /**
     * Module failed to load.
     */
    public static function loadFailed(string $name, string $reason): self
    {
        return new self(sprintf('Failed to load module "%s": %s', $name, $reason));
    }

    /**
     * Module failed to boot.
     */
    public static function bootFailed(string $name, string $reason): self
    {
        return new self(sprintf('Failed to boot module "%s": %s', $name, $reason));
    }
}
