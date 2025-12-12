<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Domain\Abstractions\DomainEvent;
use Lumina\DDD\Testing\InMemoryEventDispatcher;

final class InMemoryEventDispatcherTest extends TestCase
{
    private InMemoryEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new InMemoryEventDispatcher();
    }

    public function testDispatchRecordsEvent(): void
    {
        $event = new TestEvent('aggregate-1');

        $this->dispatcher->dispatch($event);

        $this->assertTrue($this->dispatcher->hasDispatched(TestEvent::class));
    }

    public function testHasDispatchedReturnsFalseWhenNotDispatched(): void
    {
        $this->assertFalse($this->dispatcher->hasDispatched(TestEvent::class));
    }

    public function testListenerIsCalledOnDispatch(): void
    {
        $called = false;
        $this->dispatcher->listen(TestEvent::class, function (TestEvent $event) use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new TestEvent('test'));

        $this->assertTrue($called);
    }

    public function testStopPropagationPreventsHandlerCalls(): void
    {
        $called = false;
        $this->dispatcher->listen(TestEvent::class, function () use (&$called) {
            $called = true;
        });
        $this->dispatcher->stopPropagation();

        $this->dispatcher->dispatch(new TestEvent('test'));

        $this->assertFalse($called);
    }

    public function testResumePropagationAllowsHandlerCalls(): void
    {
        $called = false;
        $this->dispatcher->listen(TestEvent::class, function () use (&$called) {
            $called = true;
        });
        $this->dispatcher->stopPropagation();
        $this->dispatcher->resumePropagation();

        $this->dispatcher->dispatch(new TestEvent('test'));

        $this->assertTrue($called);
    }

    public function testGetDispatchCountReturnsCorrectCount(): void
    {
        $this->dispatcher->dispatch(new TestEvent('1'));
        $this->dispatcher->dispatch(new TestEvent('2'));
        $this->dispatcher->dispatch(new TestEvent('3'));

        $this->assertSame(3, $this->dispatcher->getDispatchCount(TestEvent::class));
    }

    public function testGetLastEventReturnsLastDispatchedEvent(): void
    {
        $this->dispatcher->dispatch(new TestEvent('first'));
        $this->dispatcher->dispatch(new TestEvent('second'));

        $lastEvent = $this->dispatcher->getLastEvent();

        $this->assertInstanceOf(TestEvent::class, $lastEvent);
    }

    public function testGetLastEventReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->dispatcher->getLastEvent());
    }

    public function testGetFirstOfTypeReturnsFirstMatchingEvent(): void
    {
        $this->dispatcher->dispatch(new TestEvent('first'));
        $this->dispatcher->dispatch(new TestEvent('second'));

        $firstEvent = $this->dispatcher->getFirstOfType(TestEvent::class);

        $this->assertNotNull($firstEvent);
    }

    public function testResetClearsAllState(): void
    {
        $this->dispatcher->dispatch(new TestEvent('test'));
        $this->dispatcher->listen(TestEvent::class, fn() => null);

        $this->dispatcher->reset();

        $this->assertFalse($this->dispatcher->hasDispatched(TestEvent::class));
        $this->assertEmpty($this->dispatcher->getDispatchedEvents());
    }

    public function testAssertNothingDispatchedPassesWhenEmpty(): void
    {
        $this->dispatcher->assertNothingDispatched();
        $this->assertTrue(true);
    }

    public function testAssertNothingDispatchedThrowsWhenEventsDispatched(): void
    {
        $this->dispatcher->dispatch(new TestEvent('test'));

        $this->expectException(\RuntimeException::class);

        $this->dispatcher->assertNothingDispatched();
    }

    public function testAssertDispatchedWithFindsMatchingEvent(): void
    {
        $this->dispatcher->dispatch(new TestEvent('aggregate-123'));

        // Should not throw
        $this->dispatcher->assertDispatchedWith(
            TestEvent::class,
            fn(DomainEvent $e) => $e->getAggregateId() === 'aggregate-123'
        );

        $this->assertTrue(true);
    }

    public function testAssertDispatchedWithThrowsWhenNoMatch(): void
    {
        $this->dispatcher->dispatch(new TestEvent('aggregate-123'));

        $this->expectException(\RuntimeException::class);

        $this->dispatcher->assertDispatchedWith(
            TestEvent::class,
            fn(DomainEvent $e) => $e->getAggregateId() === 'different-id'
        );
    }
}

class TestEvent extends DomainEvent
{
    public function __construct(string $aggregateId)
    {
        parent::__construct($aggregateId);
    }
}
