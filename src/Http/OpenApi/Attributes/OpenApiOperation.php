<?php

declare(strict_types=1);

namespace Luminor\Http\OpenApi\Attributes;

use Attribute;

/**
 * Attribute to document API operations.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class OpenApiOperation
{
    public function __construct(
        public readonly string $summary,
        public readonly string $description = '',
        public readonly array $tags = [],
        public readonly bool $deprecated = false
    ) {
    }
}
