<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\Bus;

use Luminor\DDD\Application\CQRS\Query;

/**
 * Interface for query handlers.
 *
 * Query handlers retrieve and return data without modifying state.
 * Each handler is responsible for a single query type and may access
 * read models or repositories to fetch the requested data.
 *
 * @template TQuery of Query
 * @template TResult
 */
interface QueryHandlerInterface
{
    /**
     * Handle a query and return the result.
     *
     * @param TQuery $query The query to handle
     *
     * @return TResult The query result
     */
    public function handle(Query $query): mixed;
}
