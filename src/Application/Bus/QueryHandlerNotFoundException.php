<?php

declare(strict_types=1);

namespace Lumina\DDD\Application\Bus;

use RuntimeException;
use Lumina\DDD\Application\CQRS\Query;

/**
 * Exception thrown when no handler is found for a query.
 */
final class QueryHandlerNotFoundException extends RuntimeException
{
    /**
     * Create exception for a query class.
     *
     * @param class-string<Query> $queryClass
     */
    public static function forQuery(string $queryClass): self
    {
        return new self(
            sprintf('No handler registered for query "%s"', $queryClass)
        );
    }
}
