<?php

declare(strict_types=1);

namespace Luminor\DDD\Multitenancy;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when access to a resource from another tenant is denied.
 */
final class TenantAccessDeniedException extends RuntimeException
{
    public function __construct(
        string $message = 'Access denied: resource belongs to a different tenant.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for entity access.
     */
    public static function forEntity(string $entityType, mixed $entityId): self
    {
        return new self(sprintf(
            'Access denied: %s with ID "%s" belongs to a different tenant.',
            $entityType,
            $entityId,
        ));
    }

    /**
     * Create exception for operation.
     */
    public static function forOperation(string $operation): self
    {
        return new self(sprintf(
            'Access denied: cannot perform "%s" on resource belonging to a different tenant.',
            $operation,
        ));
    }
}
