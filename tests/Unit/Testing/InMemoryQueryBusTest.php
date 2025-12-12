<?php

declare(strict_types=1);

namespace Lumina\DDD\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Lumina\DDD\Application\CQRS\Query;
use Lumina\DDD\Testing\InMemoryQueryBus;

final class InMemoryQueryBusTest extends TestCase
{
    private InMemoryQueryBus $bus;

    protected function setUp(): void
    {
        $this->bus = new InMemoryQueryBus();
    }

    public function testDispatchRecordsQuery(): void
    {
        $query = new TestQuery('test');

        $this->bus->dispatch($query);

        $this->assertTrue($this->bus->hasDispatched(TestQuery::class));
    }

    public function testHasDispatchedReturnsFalseWhenNotDispatched(): void
    {
        $this->assertFalse($this->bus->hasDispatched(TestQuery::class));
    }

    public function testWillReturnReturnsPredefinedResult(): void
    {
        $this->bus->willReturn(TestQuery::class, ['result' => 'data']);

        $result = $this->bus->dispatch(new TestQuery('test'));

        $this->assertSame(['result' => 'data'], $result);
    }

    public function testHandlerIsCalledOnDispatch(): void
    {
        $this->bus->handle(TestQuery::class, function (TestQuery $query) {
            return 'handler result: ' . $query->value;
        });

        $result = $this->bus->dispatch(new TestQuery('test'));

        $this->assertSame('handler result: test', $result);
    }

    public function testThrowsExceptionWhenConfigured(): void
    {
        $exception = new \RuntimeException('Query failed');
        $this->bus->throwWhen(TestQuery::class, $exception);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query failed');

        $this->bus->dispatch(new TestQuery('test'));
    }

    public function testGetDispatchCountReturnsCorrectCount(): void
    {
        $this->bus->dispatch(new TestQuery('1'));
        $this->bus->dispatch(new TestQuery('2'));

        $this->assertSame(2, $this->bus->getDispatchCount(TestQuery::class));
    }

    public function testGetLastQueryReturnsLastDispatchedQuery(): void
    {
        $this->bus->dispatch(new TestQuery('first'));
        $this->bus->dispatch(new TestQuery('second'));

        $lastQuery = $this->bus->getLastQuery();

        $this->assertInstanceOf(TestQuery::class, $lastQuery);
        $this->assertSame('second', $lastQuery->value);
    }

    public function testResetClearsAllState(): void
    {
        $this->bus->dispatch(new TestQuery('test'));
        $this->bus->willReturn(TestQuery::class, 'result');

        $this->bus->reset();

        $this->assertFalse($this->bus->hasDispatched(TestQuery::class));
        $this->assertNull($this->bus->dispatch(new TestQuery('test2')));
    }

    public function testAssertNothingDispatchedPassesWhenEmpty(): void
    {
        $this->bus->assertNothingDispatched();
        $this->assertTrue(true);
    }

    public function testAssertNothingDispatchedThrowsWhenQueriesDispatched(): void
    {
        $this->bus->dispatch(new TestQuery('test'));

        $this->expectException(\RuntimeException::class);

        $this->bus->assertNothingDispatched();
    }
}

class TestQuery implements Query
{
    public function __construct(public readonly string $value)
    {
    }
}
