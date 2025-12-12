<?php

declare(strict_types=1);

namespace Lumina\DDD\Application\Bus;

use Lumina\DDD\Application\CQRS\Query;

/**
 * Interface for the query bus.
 *
 * The query bus routes queries to their appropriate handlers.
 * It provides a single entry point for executing queries and can
 * apply middleware for cross-cutting concerns like caching and logging.
 */
interface QueryBusInterface
{
    /**
     * Dispatch a query to its handler.
     *
     * @template TResult
     * @param Query $query The query to dispatch
     * @return TResult The result from the query handler
     * @throws QueryHandlerNotFoundException If no handler is registered for the query
     */
    public function dispatch(Query $query): mixed;

    /**
     * Register a handler for a specific query type.
     *
     * @param class-string<Query> $queryClass The query class
     * @param QueryHandlerInterface $handler The handler instance
     */
    public function register(string $queryClass, QueryHandlerInterface $handler): void;

    /**
     * Register a handler using a callable resolver.
     *
     * The resolver will be called to create the handler instance when needed.
     *
     * @param class-string<Query> $queryClass The query class
     * @param callable(): QueryHandlerInterface $resolver The handler resolver
     */
    public function registerLazy(string $queryClass, callable $resolver): void;

    /**
     * Check if a handler is registered for a query type.
     *
     * @param class-string<Query> $queryClass The query class
     */
    public function hasHandler(string $queryClass): bool;
}
