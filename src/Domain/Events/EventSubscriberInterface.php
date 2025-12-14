<?php

declare(strict_types=1);

namespace Luminor\Domain\Events;

use Luminor\Domain\Abstractions\DomainEvent;

/**
 * Interface for event subscribers that handle multiple event types.
 *
 * Unlike EventHandlerInterface which handles a single event type,
 * subscribers can define multiple handlers for different event types
 * within a single class.
 */
interface EventSubscriberInterface
{
    /**
     * Get the event subscriptions.
     *
     * Returns an array mapping event class names to method names.
     *
     * @return array<class-string<DomainEvent>, string|array<int, string>>
     *
     * Example:
     * ```php
     * return [
     *     UserCreated::class => 'onUserCreated',
     *     UserUpdated::class => ['onUserUpdated', 'sendNotification'],
     * ];
     * ```
     */
    public static function getSubscribedEvents(): array;
}
