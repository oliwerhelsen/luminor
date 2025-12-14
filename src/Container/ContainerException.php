<?php

declare(strict_types=1);

namespace Luminor\Container;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Exception thrown when the container encounters an error.
 */
final class ContainerException extends Exception implements ContainerExceptionInterface
{
    /**
     * Create exception for unresolvable class.
     */
    public static function unresolvable(string $class): self
    {
        return new self(sprintf('Unable to resolve class: %s', $class));
    }

    /**
     * Create exception for circular dependency.
     */
    public static function circularDependency(string $class): self
    {
        return new self(sprintf('Circular dependency detected while resolving: %s', $class));
    }
}
