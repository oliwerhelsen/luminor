<?php

declare(strict_types=1);

namespace Luminor\DDD\Container;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when a requested entry is not found in the container.
 */
final class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    /**
     * Create exception for a missing binding.
     */
    public static function forBinding(string $abstract): self
    {
        return new self(sprintf('No binding found for: %s', $abstract));
    }
}
