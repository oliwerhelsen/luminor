<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Bus\Middleware;

use Luminor\DDD\Application\CQRS\Command;
use Luminor\DDD\Application\CQRS\Query;
use Luminor\DDD\Infrastructure\Bus\MiddlewareInterface;
use Luminor\DDD\Infrastructure\Persistence\TransactionInterface;
use Throwable;

/**
 * Middleware that wraps command execution in a database transaction.
 *
 * Only wraps Commands, not Queries (since queries should be read-only).
 */
final class TransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TransactionInterface $transaction,
    ) {
    }

    public function handle(Command|Query $message, callable $next): mixed
    {
        // Only wrap commands in transactions
        if ($message instanceof Query) {
            return $next($message);
        }

        $this->transaction->begin();

        try {
            $result = $next($message);
            $this->transaction->commit();

            return $result;
        } catch (Throwable $e) {
            $this->transaction->rollback();

            throw $e;
        }
    }
}
