<?php

declare(strict_types=1);

namespace Luminor\DDD\Testing\Assertions;

use DateTimeImmutable;
use Luminor\DDD\Domain\Abstractions\AggregateRoot;
use Luminor\DDD\Domain\Abstractions\DomainEvent;
use Luminor\DDD\Domain\Abstractions\Entity;
use Luminor\DDD\Domain\Abstractions\ValueObject;
use PHPUnit\Framework\Assert;

/**
 * Domain-specific assertions for testing.
 *
 * Provides assertion methods tailored for testing domain entities,
 * value objects, aggregates, and events.
 */
trait DomainAssertions
{
    /**
     * Assert that two entities are equal by identity.
     */
    public static function assertEntitiesEqual(Entity $expected, Entity $actual, string $message = ''): void
    {
        Assert::assertTrue(
            $expected->equals($actual),
            $message ?: sprintf(
                'Failed asserting that entity with ID [%s] equals entity with ID [%s].',
                $actual->getId() ?? 'null',
                $expected->getId() ?? 'null',
            ),
        );
    }

    /**
     * Assert that two entities are not equal.
     */
    public static function assertEntitiesNotEqual(Entity $expected, Entity $actual, string $message = ''): void
    {
        Assert::assertFalse(
            $expected->equals($actual),
            $message ?: 'Failed asserting that entities are not equal.',
        );
    }

    /**
     * Assert that two value objects are equal.
     */
    public static function assertValueObjectsEqual(ValueObject $expected, ValueObject $actual, string $message = ''): void
    {
        Assert::assertTrue(
            $expected->equals($actual),
            $message ?: 'Failed asserting that value objects are equal.',
        );
    }

    /**
     * Assert that two value objects are not equal.
     */
    public static function assertValueObjectsNotEqual(ValueObject $expected, ValueObject $actual, string $message = ''): void
    {
        Assert::assertFalse(
            $expected->equals($actual),
            $message ?: 'Failed asserting that value objects are not equal.',
        );
    }

    /**
     * Assert that an aggregate has recorded a specific event type.
     *
     * @param class-string<DomainEvent> $eventClass
     */
    public static function assertAggregateHasEvent(AggregateRoot $aggregate, string $eventClass, string $message = ''): void
    {
        $events = $aggregate->pullEvents();
        $hasEvent = false;

        foreach ($events as $event) {
            if ($event instanceof $eventClass) {
                $hasEvent = true;
                break;
            }
        }

        Assert::assertTrue(
            $hasEvent,
            $message ?: sprintf('Failed asserting that aggregate has event [%s].', $eventClass),
        );
    }

    /**
     * Assert that an aggregate has not recorded a specific event type.
     *
     * @param class-string<DomainEvent> $eventClass
     */
    public static function assertAggregateDoesNotHaveEvent(AggregateRoot $aggregate, string $eventClass, string $message = ''): void
    {
        $events = $aggregate->pullEvents();
        $hasEvent = false;

        foreach ($events as $event) {
            if ($event instanceof $eventClass) {
                $hasEvent = true;
                break;
            }
        }

        Assert::assertFalse(
            $hasEvent,
            $message ?: sprintf('Failed asserting that aggregate does not have event [%s].', $eventClass),
        );
    }

    /**
     * Assert that an aggregate has recorded a specific number of events.
     */
    public static function assertAggregateEventCount(AggregateRoot $aggregate, int $expectedCount, string $message = ''): void
    {
        $events = $aggregate->pullEvents();

        Assert::assertCount(
            $expectedCount,
            $events,
            $message ?: sprintf('Failed asserting that aggregate has %d events.', $expectedCount),
        );
    }

    /**
     * Assert that an aggregate has no recorded events.
     */
    public static function assertAggregateHasNoEvents(AggregateRoot $aggregate, string $message = ''): void
    {
        $events = $aggregate->pullEvents();

        Assert::assertEmpty(
            $events,
            $message ?: 'Failed asserting that aggregate has no recorded events.',
        );
    }

    /**
     * Assert that an entity is not transient (has an ID).
     */
    public static function assertEntityNotTransient(Entity $entity, string $message = ''): void
    {
        Assert::assertFalse(
            $entity->isTransient(),
            $message ?: 'Failed asserting that entity is not transient.',
        );
    }

    /**
     * Assert that an entity is transient (has no ID).
     */
    public static function assertEntityTransient(Entity $entity, string $message = ''): void
    {
        Assert::assertTrue(
            $entity->isTransient(),
            $message ?: 'Failed asserting that entity is transient.',
        );
    }

    /**
     * Assert that an entity has a specific ID.
     */
    public static function assertEntityHasId(Entity $entity, string $expectedId, string $message = ''): void
    {
        Assert::assertSame(
            $expectedId,
            $entity->getId(),
            $message ?: sprintf('Failed asserting that entity has ID [%s].', $expectedId),
        );
    }

    /**
     * Assert that a domain event has specific aggregate ID.
     */
    public static function assertEventAggregateId(DomainEvent $event, string $expectedAggregateId, string $message = ''): void
    {
        Assert::assertSame(
            $expectedAggregateId,
            $event->getAggregateId(),
            $message ?: sprintf('Failed asserting that event has aggregate ID [%s].', $expectedAggregateId),
        );
    }

    /**
     * Assert that a domain event occurred at approximately the expected time.
     */
    public static function assertEventOccurredAt(
        DomainEvent $event,
        DateTimeImmutable $expectedTime,
        int $toleranceSeconds = 1,
        string $message = '',
    ): void {
        $diff = abs($event->getOccurredAt()->getTimestamp() - $expectedTime->getTimestamp());

        Assert::assertLessThanOrEqual(
            $toleranceSeconds,
            $diff,
            $message ?: sprintf(
                'Failed asserting that event occurred within %d seconds of expected time.',
                $toleranceSeconds,
            ),
        );
    }
}
