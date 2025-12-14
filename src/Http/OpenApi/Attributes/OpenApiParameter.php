<?php

declare(strict_types=1);

namespace Luminor\Http\OpenApi\Attributes;

use Attribute;

/**
 * Attribute to document API parameters.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OpenApiParameter
{
    public function __construct(
        public readonly string $name,
        public readonly string $in = 'query',
        public readonly string $description = '',
        public readonly bool $required = false,
        public readonly string $type = 'string',
        public readonly mixed $example = null
    ) {
    }
}
