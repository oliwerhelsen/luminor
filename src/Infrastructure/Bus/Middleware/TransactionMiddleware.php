<?php

declare(strict_types=1);

namespace Lumina\DDD\Infrastructure\Bus\Middleware;

use Lumina\DDD\Application\CQRS\Command;
use Lumina\DDD\Application\CQRS\Query;
use Lumina\DDD\Infrastructure\Bus\MiddlewareInterface;
use Lumina\DDD\Infrastructure\Persistence\TransactionInterface;

/**
 * Middleware that wraps command execution in a database transaction.
 *
 * Only wraps Commands, not Queries (since queries should be read-only).
 */
final class TransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TransactionInterface $transaction
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
        } catch (\Throwable $e) {
            $this->transaction->rollback();
            throw $e;
        }
    }
}
