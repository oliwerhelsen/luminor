<?php

declare(strict_types=1);

namespace Luminor\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Luminor\Application\CQRS\Command;
use Luminor\Testing\InMemoryCommandBus;

final class InMemoryCommandBusTest extends TestCase
{
    private InMemoryCommandBus $bus;

    protected function setUp(): void
    {
        $this->bus = new InMemoryCommandBus();
    }

    public function testDispatchRecordsCommand(): void
    {
        $command = new TestCommand('test');

        $this->bus->dispatch($command);

        $this->assertTrue($this->bus->hasDispatched(TestCommand::class));
    }

    public function testHasDispatchedReturnsFalseWhenNotDispatched(): void
    {
        $this->assertFalse($this->bus->hasDispatched(TestCommand::class));
    }

    public function testGetDispatchCountReturnsCorrectCount(): void
    {
        $this->bus->dispatch(new TestCommand('1'));
        $this->bus->dispatch(new TestCommand('2'));
        $this->bus->dispatch(new TestCommand('3'));

        $this->assertSame(3, $this->bus->getDispatchCount(TestCommand::class));
    }

    public function testHandlerIsCalledOnDispatch(): void
    {
        $called = false;
        $this->bus->handle(TestCommand::class, function (TestCommand $cmd) use (&$called) {
            $called = true;
            return 'result';
        });

        $result = $this->bus->dispatch(new TestCommand('test'));

        $this->assertTrue($called);
        $this->assertSame('result', $result);
    }

    public function testThrowsExceptionWhenConfigured(): void
    {
        $exception = new \RuntimeException('Test exception');
        $this->bus->throwWhen(TestCommand::class, $exception);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->bus->dispatch(new TestCommand('test'));
    }

    public function testGetLastCommandReturnsLastDispatchedCommand(): void
    {
        $this->bus->dispatch(new TestCommand('first'));
        $this->bus->dispatch(new TestCommand('second'));

        $lastCommand = $this->bus->getLastCommand();

        $this->assertInstanceOf(TestCommand::class, $lastCommand);
        $this->assertSame('second', $lastCommand->value);
    }

    public function testGetLastCommandReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->bus->getLastCommand());
    }

    public function testResetClearsAllState(): void
    {
        $this->bus->dispatch(new TestCommand('test'));
        $this->bus->handle(TestCommand::class, fn() => null);

        $this->bus->reset();

        $this->assertFalse($this->bus->hasDispatched(TestCommand::class));
        $this->assertEmpty($this->bus->getDispatchedCommands());
    }

    public function testGetDispatchedOfTypeReturnsFilteredCommands(): void
    {
        $this->bus->dispatch(new TestCommand('1'));
        $this->bus->dispatch(new AnotherTestCommand());
        $this->bus->dispatch(new TestCommand('2'));

        $commands = $this->bus->getDispatchedOfType(TestCommand::class);

        $this->assertCount(2, $commands);
    }

    public function testAssertNothingDispatchedPassesWhenEmpty(): void
    {
        // Should not throw
        $this->bus->assertNothingDispatched();
        $this->assertTrue(true);
    }

    public function testAssertNothingDispatchedThrowsWhenCommandsDispatched(): void
    {
        $this->bus->dispatch(new TestCommand('test'));

        $this->expectException(\RuntimeException::class);

        $this->bus->assertNothingDispatched();
    }
}

class TestCommand implements Command
{
    public function __construct(public readonly string $value)
    {
    }
}

class AnotherTestCommand implements Command
{
}
