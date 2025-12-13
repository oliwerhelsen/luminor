<?php

declare(strict_types=1);

namespace Luminor\DDD\Tests\Unit\Domain\Events;

use Luminor\DDD\Domain\Abstractions\DomainEvent;
use Luminor\DDD\Domain\Events\EventSourcedAggregateRoot;
use PHPUnit\Framework\TestCase;

final class EventSourcedAggregateRootTest extends TestCase
{
    public function testReconstituteFromEvents(): void
    {
        $aggregateId = 'test-123';
        $events = [
            new TestCreatedEvent($aggregateId, 'Initial'),
            new TestUpdatedEvent($aggregateId, 'Updated'),
        ];

        $aggregate = TestAggregate::reconstituteFromEvents($events);

        $this->assertEquals($aggregateId, $aggregate->getId());
        $this->assertEquals(2, $aggregate->getVersion());
        $this->assertEquals('Updated', $aggregate->getName());
    }

    public function testReconstituteFromEmptyEventStreamThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestAggregate::reconstituteFromEvents([]);
    }

    public function testRecordEventIncrementsVersion(): void
    {
        $aggregate = TestAggregate::create('Test');

        $this->assertEquals(1, $aggregate->getVersion());

        $aggregate->updateName('Updated');

        $this->assertEquals(2, $aggregate->getVersion());
    }

    public function testPullDomainEvents(): void
    {
        $aggregate = TestAggregate::create('Test');
        $aggregate->updateName('Updated');

        $events = $aggregate->pullDomainEvents();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(TestCreatedEvent::class, $events[0]);
        $this->assertInstanceOf(TestUpdatedEvent::class, $events[1]);
    }
}

// Test fixtures

final class TestAggregate extends EventSourcedAggregateRoot
{
    private string $name;

    private function __construct(string $id)
    {
        parent::__construct($id);
    }

    public static function create(string $name): self
    {
        $aggregate = new self(self::generateId());
        $aggregate->recordEvent(new TestCreatedEvent($aggregate->getId(), $name));
        return $aggregate;
    }

    public function updateName(string $name): void
    {
        $this->recordEvent(new TestUpdatedEvent($this->getId(), $name));
    }

    protected function applyTestCreatedEvent(TestCreatedEvent $event): void
    {
        $this->name = $event->getPayload()['name'];
    }

    protected function applyTestUpdatedEvent(TestUpdatedEvent $event): void
    {
        $this->name = $event->getPayload()['name'];
    }

    public function getName(): string
    {
        return $this->name;
    }
}

final class TestCreatedEvent extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private readonly string $name
    ) {
        parent::__construct($aggregateId);
    }

    public function getPayload(): array
    {
        return ['name' => $this->name];
    }
}

final class TestUpdatedEvent extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private readonly string $name
    ) {
        parent::__construct($aggregateId);
    }

    public function getPayload(): array
    {
        return ['name' => $this->name];
    }
}
