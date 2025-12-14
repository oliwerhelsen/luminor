<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Orders\Domain\Events;

use Luminor\Domain\Abstractions\DomainEvent;

/**
 * Event raised when an order is completed.
 */
final class OrderCompletedEvent extends DomainEvent
{
    public function __construct(string $orderId)
    {
        parent::__construct($orderId);
    }
}
