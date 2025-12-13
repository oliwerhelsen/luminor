<?php

declare(strict_types=1);

namespace Example\BasicApi\Application\DTOs;

use Luminor\DDD\Application\DTO\DataTransferObject;
use Example\BasicApi\Domain\Entities\Product;

/**
 * Product DTO for API responses.
 */
final class ProductDto extends DataTransferObject
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceInCents,
        public readonly string $priceFormatted,
        public readonly string $currency,
        public readonly int $stock,
        public readonly bool $inStock,
    ) {
    }

    /**
     * Create DTO from entity.
     */
    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId(),
            name: $product->getName(),
            description: $product->getDescription(),
            priceInCents: $product->getPrice()->getAmount(),
            priceFormatted: $product->getPrice()->getFormatted(),
            currency: $product->getPrice()->getCurrency(),
            stock: $product->getStock(),
            inStock: $product->isInStock(),
        );
    }
}
