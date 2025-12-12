<?php

declare(strict_types=1);

namespace Lumina\DDD\Testing;

use Lumina\DDD\Application\Bus\QueryBusInterface;
use Lumina\DDD\Application\Bus\QueryHandlerInterface;
use Lumina\DDD\Application\CQRS\Query;

/**
 * In-memory query bus for testing.
 *
 * Records all dispatched queries and allows setting up
 * predefined results for testing scenarios.
 */
final class InMemoryQueryBus implements QueryBusInterface
{
    /** @var array<Query> */
    private array $dispatchedQueries = [];

    /** @var array<class-string, callable> */
    private array $handlers = [];

    /** @var array<class-string, mixed> */
    private array $results = [];

    /** @var array<class-string, \Throwable> */
    private array $exceptions = [];

    /**
     * @inheritDoc
     */
    public function dispatch(Query $query): mixed
    {
        $queryClass = $query::class;
        $this->dispatchedQueries[] = $query;

        // Check if we should throw an exception
        if (isset($this->exceptions[$queryClass])) {
            throw $this->exceptions[$queryClass];
        }

        // Check if we have a predefined result
        if (array_key_exists($queryClass, $this->results)) {
            return $this->results[$queryClass];
        }

        // Check if we have a handler
        if (isset($this->handlers[$queryClass])) {
            return ($this->handlers[$queryClass])($query);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function registerHandler(string $queryClass, QueryHandlerInterface $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    /**
     * Register a handler callable for a query.
     *
     * @param class-string<Query> $queryClass
     */
    public function handle(string $queryClass, callable $handler): self
    {
        $this->handlers[$queryClass] = $handler;
        return $this;
    }

    /**
     * Set a predefined result for a query.
     *
     * @param class-string<Query> $queryClass
     */
    public function willReturn(string $queryClass, mixed $result): self
    {
        $this->results[$queryClass] = $result;
        return $this;
    }

    /**
     * Set an exception to be thrown when a query is dispatched.
     *
     * @param class-string<Query> $queryClass
     */
    public function throwWhen(string $queryClass, \Throwable $exception): self
    {
        $this->exceptions[$queryClass] = $exception;
        return $this;
    }

    /**
     * Check if a query was dispatched.
     *
     * @param class-string<Query> $queryClass
     */
    public function hasDispatched(string $queryClass): bool
    {
        foreach ($this->dispatchedQueries as $query) {
            if ($query instanceof $queryClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the number of times a query was dispatched.
     *
     * @param class-string<Query> $queryClass
     */
    public function getDispatchCount(string $queryClass): int
    {
        $count = 0;
        foreach ($this->dispatchedQueries as $query) {
            if ($query instanceof $queryClass) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get all dispatched queries.
     *
     * @return array<Query>
     */
    public function getDispatchedQueries(): array
    {
        return $this->dispatchedQueries;
    }

    /**
     * Get the last dispatched query.
     */
    public function getLastQuery(): ?Query
    {
        $count = count($this->dispatchedQueries);
        return $count > 0 ? $this->dispatchedQueries[$count - 1] : null;
    }

    /**
     * Get dispatched queries of a specific type.
     *
     * @param class-string<Query> $queryClass
     * @return array<Query>
     */
    public function getDispatchedOfType(string $queryClass): array
    {
        return array_filter(
            $this->dispatchedQueries,
            fn(Query $query) => $query instanceof $queryClass
        );
    }

    /**
     * Reset the bus state.
     */
    public function reset(): void
    {
        $this->dispatchedQueries = [];
        $this->handlers = [];
        $this->results = [];
        $this->exceptions = [];
    }

    /**
     * Assert that no queries were dispatched.
     *
     * @throws \RuntimeException if queries were dispatched
     */
    public function assertNothingDispatched(): void
    {
        if (count($this->dispatchedQueries) > 0) {
            $classes = array_map(
                fn(Query $q) => $q::class,
                $this->dispatchedQueries
            );
            throw new \RuntimeException(
                sprintf('Expected no queries to be dispatched, but [%s] were dispatched.', implode(', ', $classes))
            );
        }
    }
}
