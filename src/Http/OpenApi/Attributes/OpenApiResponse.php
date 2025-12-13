<?php

declare(strict_types=1);

namespace Luminor\DDD\Http\OpenApi\Attributes;

use Attribute;

/**
 * Attribute to document API responses.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OpenApiResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $description,
        public readonly ?string $schema = null,
        public readonly ?array $example = null
    ) {
    }
}
