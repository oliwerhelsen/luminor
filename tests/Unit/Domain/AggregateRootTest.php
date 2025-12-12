<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Domain\Abstractions\AggregateRoot;
use Lumina\DDD\Domain\Abstractions\DomainEvent;

final class AggregateRootTest extends TestCase
{
    public function testAggregateRootStartsWithNoEvents(): void
    {
        $aggregate = new ConcreteAggregateRoot('test-id');

        $this->assertEmpty($aggregate->getDomainEvents());
        $this->assertFalse($aggregate->hasDomainEvents());
    }

    public function testAggregateRootCanRecordEvents(): void
    {
        $aggregate = new ConcreteAggregateRoot('test-id');
        $aggregate->doSomething();

        $this->assertCount(1, $aggregate->getDomainEvents());
        $this->assertTrue($aggregate->hasDomainEvents());
    }

    public function testGetDomainEventsReturnsAllRecordedEvents(): void
    {
        $aggregate = new ConcreteAggregateRoot('test-id');
        $aggregate->doSomething();
        $aggregate->doSomething();

        $events = $aggregate->getDomainEvents();

        $this->assertCount(2, $events);
        $this->assertContainsOnlyInstancesOf(DomainEvent::class, $events);
    }

    public function testClearDomainEventsRemovesAllEvents(): void
    {
        $aggregate = new ConcreteAggregateRoot('test-id');
        $aggregate->doSomething();
        $aggregate->doSomething();

        $aggregate->clearDomainEvents();

        $this->assertEmpty($aggregate->getDomainEvents());
        $this->assertFalse($aggregate->hasDomainEvents());
    }

    public function testPullDomainEventsReturnsAndClearsEvents(): void
    {
        $aggregate = new ConcreteAggregateRoot('test-id');
        $aggregate->doSomething();
        $aggregate->doSomething();

        $events = $aggregate->pullDomainEvents();

        $this->assertCount(2, $events);
        $this->assertEmpty($aggregate->getDomainEvents());
    }

    public function testAggregateRootInheritsEntityBehavior(): void
    {
        $aggregate1 = new ConcreteAggregateRoot('test-id');
        $aggregate2 = new ConcreteAggregateRoot('test-id');

        $this->assertTrue($aggregate1->equals($aggregate2));
        $this->assertSame('test-id', $aggregate1->getId());
    }
}

/**
 * @extends AggregateRoot<string>
 */
final class ConcreteAggregateRoot extends AggregateRoot
{
    public function __construct(string $id)
    {
        parent::__construct($id);
    }

    public function doSomething(): void
    {
        $this->recordEvent(new SomethingHappened((string) $this->getId()));
    }
}

final class SomethingHappened extends DomainEvent
{
    public function __construct(string $aggregateId)
    {
        parent::__construct($aggregateId);
    }
}
