<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Commands;

use Luminor\DDD\Application\CQRS\Command;

/**
 * Command to create a new product.
 */
final class CreateProductCommand implements Command
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceInCents,
        public readonly string $currency = 'USD',
        public readonly int $stock = 0,
    ) {
    }
}
