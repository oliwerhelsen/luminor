<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Queries;

use Lumina\DDD\Application\CQRS\Query;

/**
 * Query to get a single product by ID.
 */
final class GetProductQuery implements Query
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
