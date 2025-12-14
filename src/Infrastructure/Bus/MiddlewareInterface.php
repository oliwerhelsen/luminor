<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Bus;

use Luminor\Application\CQRS\Command;
use Luminor\Application\CQRS\Query;

/**
 * Interface for bus middleware.
 *
 * Middleware can intercept commands/queries before they reach
 * their handlers, enabling cross-cutting concerns like logging,
 * validation, and transaction management.
 */
interface MiddlewareInterface
{
    /**
     * Handle a command or query.
     *
     * @param Command|Query $message The message to handle
     * @param callable(Command|Query): mixed $next The next handler in the pipeline
     * @return mixed The result from the handler
     */
    public function handle(Command|Query $message, callable $next): mixed;
}
