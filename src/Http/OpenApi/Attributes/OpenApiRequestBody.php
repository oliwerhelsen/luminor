<?php

declare(strict_types=1);

namespace Luminor\Http\OpenApi\Attributes;

use Attribute;

/**
 * Attribute to document API request bodies.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class OpenApiRequestBody
{
    public function __construct(
        public readonly string $description = '',
        public readonly bool $required = true,
        public readonly ?string $schema = null,
        public readonly ?array $example = null
    ) {
    }
}
