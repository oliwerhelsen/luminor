<?php

declare(strict_types=1);

namespace Luminor\Tests\Unit\Infrastructure\Persistence;

use DateTimeImmutable;
use Luminor\Domain\Abstractions\DomainEvent;
use Luminor\Infrastructure\Persistence\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

final class InMemoryEventStoreTest extends TestCase
{
    private InMemoryEventStore $eventStore;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
    }

    public function testAppendEvent(): void
    {
        $event = $this->createEvent('aggregate-1');

        $this->eventStore->append($event);

        $this->assertEquals(1, $this->eventStore->count());
    }

    public function testAppendMultipleEvents(): void
    {
        $events = [
            $this->createEvent('aggregate-1'),
            $this->createEvent('aggregate-1'),
            $this->createEvent('aggregate-2'),
        ];

        $this->eventStore->appendAll($events);

        $this->assertEquals(3, $this->eventStore->count());
    }

    public function testGetEventsForAggregate(): void
    {
        $this->eventStore->append($this->createEvent('aggregate-1'));
        $this->eventStore->append($this->createEvent('aggregate-1'));
        $this->eventStore->append($this->createEvent('aggregate-2'));

        $events = $this->eventStore->getEventsForAggregate('aggregate-1');

        $this->assertCount(2, $events);
    }

    public function testGetAggregateVersion(): void
    {
        $this->eventStore->append($this->createEvent('aggregate-1'));
        $this->eventStore->append($this->createEvent('aggregate-1'));
        $this->eventStore->append($this->createEvent('aggregate-2'));

        $this->assertEquals(2, $this->eventStore->getAggregateVersion('aggregate-1'));
        $this->assertEquals(1, $this->eventStore->getAggregateVersion('aggregate-2'));
        $this->assertEquals(0, $this->eventStore->getAggregateVersion('aggregate-3'));
    }

    public function testGetEventsByType(): void
    {
        $event1 = $this->createEvent('aggregate-1');
        $event2 = $this->createEvent('aggregate-2');

        $this->eventStore->append($event1);
        $this->eventStore->append($event2);

        $events = $this->eventStore->getEventsByType(get_class($event1));

        $this->assertCount(2, $events);
    }

    public function testGetEventsAfter(): void
    {
        $past = new DateTimeImmutable('-1 hour');
        $future = new DateTimeImmutable('+1 hour');

        $this->eventStore->append($this->createEvent('aggregate-1'));

        $eventsAfterPast = $this->eventStore->getEventsAfter($past);
        $eventsAfterFuture = $this->eventStore->getEventsAfter($future);

        $this->assertCount(1, $eventsAfterPast);
        $this->assertCount(0, $eventsAfterFuture);
    }

    public function testClear(): void
    {
        $this->eventStore->append($this->createEvent('aggregate-1'));
        $this->eventStore->append($this->createEvent('aggregate-2'));

        $this->assertEquals(2, $this->eventStore->count());

        $this->eventStore->clear();

        $this->assertEquals(0, $this->eventStore->count());
    }

    private function createEvent(string $aggregateId): DomainEvent
    {
        return new class($aggregateId) extends DomainEvent {
            public function __construct(string $aggregateId)
            {
                parent::__construct($aggregateId);
            }

            public function getPayload(): array
            {
                return ['test' => 'data'];
            }
        };
    }
}
