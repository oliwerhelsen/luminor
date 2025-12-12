<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Queries;

use Lumina\DDD\Application\CQRS\Query;

/**
 * Query to list products with pagination.
 */
final class ListProductsQuery implements Query
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {
    }
}
