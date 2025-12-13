<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Domain;

use DateTimeImmutable;
use Luminor\DDD\Domain\Abstractions\DomainEvent;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function testEventHasUniqueId(): void
    {
        $event1 = new UserCreated('user-1', 'john@example.com');
        $event2 = new UserCreated('user-1', 'john@example.com');

        $this->assertNotEmpty($event1->getEventId());
        $this->assertNotEmpty($event2->getEventId());
        $this->assertNotSame($event1->getEventId(), $event2->getEventId());
    }

    public function testEventHasOccurredOnTimestamp(): void
    {
        $before = new DateTimeImmutable();
        $event = new UserCreated('user-1', 'john@example.com');
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->getOccurredOn());
        $this->assertLessThanOrEqual($after, $event->getOccurredOn());
    }

    public function testEventHasAggregateId(): void
    {
        $event = new UserCreated('user-1', 'john@example.com');

        $this->assertSame('user-1', $event->getAggregateId());
    }

    public function testEventAggregateIdCanBeNull(): void
    {
        $event = new SystemStarted();

        $this->assertNull($event->getAggregateId());
    }

    public function testEventNameReturnsClassName(): void
    {
        $event = new UserCreated('user-1', 'john@example.com');

        $this->assertSame('UserCreated', $event->getEventName());
    }

    public function testEventTypeReturnsFullClassName(): void
    {
        $event = new UserCreated('user-1', 'john@example.com');

        $this->assertSame(UserCreated::class, $event->getEventType());
    }

    public function testToArrayContainsAllEventData(): void
    {
        $event = new UserCreated('user-1', 'john@example.com');

        $array = $event->toArray();

        $this->assertArrayHasKey('eventId', $array);
        $this->assertArrayHasKey('eventType', $array);
        $this->assertArrayHasKey('eventName', $array);
        $this->assertArrayHasKey('aggregateId', $array);
        $this->assertArrayHasKey('occurredOn', $array);
        $this->assertArrayHasKey('payload', $array);

        $this->assertSame($event->getEventId(), $array['eventId']);
        $this->assertSame(UserCreated::class, $array['eventType']);
        $this->assertSame('UserCreated', $array['eventName']);
        $this->assertSame('user-1', $array['aggregateId']);
        $this->assertSame(['email' => 'john@example.com'], $array['payload']);
    }
}

final class UserCreated extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private readonly string $email,
    ) {
        parent::__construct($aggregateId);
    }

    protected function getPayload(): array
    {
        return ['email' => $this->email];
    }
}

final class SystemStarted extends DomainEvent
{
    public function __construct()
    {
        parent::__construct(null);
    }
}
