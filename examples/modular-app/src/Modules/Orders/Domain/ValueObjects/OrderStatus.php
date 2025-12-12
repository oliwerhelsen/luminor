<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders\Domain\ValueObjects;

/**
 * Order status enumeration.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
