<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Bus;

use Luminor\DDD\Application\Bus\QueryBusInterface;
use Luminor\DDD\Application\Bus\QueryHandlerInterface;
use Luminor\DDD\Application\Bus\QueryHandlerNotFoundException;
use Luminor\DDD\Application\CQRS\Query;

/**
 * Simple in-memory query bus implementation.
 *
 * Routes queries to their registered handlers.
 */
final class SimpleQueryBus implements QueryBusInterface
{
    /** @var array<class-string<Query>, QueryHandlerInterface> */
    private array $handlers = [];

    /** @var array<class-string<Query>, callable(): QueryHandlerInterface> */
    private array $lazyHandlers = [];

    /** @var array<int, MiddlewareInterface> */
    private array $middlewares = [];

    public function __construct(
        private readonly ?HandlerResolver $resolver = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function dispatch(Query $query): mixed
    {
        $queryClass = $query::class;

        // Get the handler
        $handler = $this->resolveHandler($queryClass);

        // Execute through middleware pipeline
        $execute = fn(Query $q) => $handler->handle($q);

        return $this->executeWithMiddleware($query, $execute);
    }

    /**
     * @inheritDoc
     */
    public function register(string $queryClass, QueryHandlerInterface $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    /**
     * @inheritDoc
     */
    public function registerLazy(string $queryClass, callable $resolver): void
    {
        $this->lazyHandlers[$queryClass] = $resolver;
    }

    /**
     * Register a handler by class name (requires HandlerResolver).
     *
     * @param class-string<Query> $queryClass
     * @param class-string<QueryHandlerInterface> $handlerClass
     */
    public function registerHandler(string $queryClass, string $handlerClass): void
    {
        $this->lazyHandlers[$queryClass] = fn() => $this->resolver?->resolve($handlerClass)
            ?? throw new \RuntimeException('HandlerResolver is required for class-based registration');
    }

    /**
     * @inheritDoc
     */
    public function hasHandler(string $queryClass): bool
    {
        return isset($this->handlers[$queryClass]) || isset($this->lazyHandlers[$queryClass]);
    }

    /**
     * Add middleware to the query bus.
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Resolve the handler for a query.
     *
     * @param class-string<Query> $queryClass
     */
    private function resolveHandler(string $queryClass): QueryHandlerInterface
    {
        // Check for direct handler registration
        if (isset($this->handlers[$queryClass])) {
            return $this->handlers[$queryClass];
        }

        // Check for lazy handler registration
        if (isset($this->lazyHandlers[$queryClass])) {
            $handler = ($this->lazyHandlers[$queryClass])();
            $this->handlers[$queryClass] = $handler;
            unset($this->lazyHandlers[$queryClass]);
            return $handler;
        }

        throw QueryHandlerNotFoundException::forQuery($queryClass);
    }

    /**
     * Execute query through middleware pipeline.
     *
     * @param callable(Query): mixed $finalHandler
     */
    private function executeWithMiddleware(Query $query, callable $finalHandler): mixed
    {
        if (count($this->middlewares) === 0) {
            return $finalHandler($query);
        }

        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn(callable $next, MiddlewareInterface $middleware) => fn(Query $q) => $middleware->handle($q, $next),
            $finalHandler
        );

        return $pipeline($query);
    }
}
