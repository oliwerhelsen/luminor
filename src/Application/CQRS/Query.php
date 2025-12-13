<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\CQRS;

/**
 * Marker interface for queries.
 *
 * Queries represent requests for information from the system.
 * They should not modify any state and should be side-effect free.
 *
 * Queries:
 * - Request data without modifying state
 * - Are named as questions (GetUserById, FindActiveOrders)
 * - Return data (DTOs, arrays, scalar values)
 * - Should be idempotent and cacheable
 * - Are handled by exactly one QueryHandler
 */
interface Query
{
}
