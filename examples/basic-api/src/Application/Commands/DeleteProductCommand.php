<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Commands;

use Luminor\DDD\Application\CQRS\Command;

/**
 * Command to delete a product.
 */
final class DeleteProductCommand implements Command
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
