<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\Commands;

use Lumina\DDD\Application\CQRS\Command;

/**
 * Command to update an existing product.
 */
final class UpdateProductCommand implements Command
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?int $priceInCents = null,
        public readonly ?string $currency = null,
        public readonly ?int $stock = null,
    ) {
    }
}
