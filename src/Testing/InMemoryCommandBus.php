<?php

declare(strict_types=1);

namespace Lumina\DDD\Testing;

use Lumina\DDD\Application\Bus\CommandBusInterface;
use Lumina\DDD\Application\Bus\CommandHandlerInterface;
use Lumina\DDD\Application\CQRS\Command;

/**
 * In-memory command bus for testing.
 *
 * Records all dispatched commands and allows setting up
 * predefined handlers or results for testing scenarios.
 */
final class InMemoryCommandBus implements CommandBusInterface
{
    /** @var array<Command> */
    private array $dispatchedCommands = [];

    /** @var array<class-string, callable> */
    private array $handlers = [];

    /** @var array<class-string, \Throwable> */
    private array $exceptions = [];

    /**
     * @inheritDoc
     */
    public function dispatch(Command $command): mixed
    {
        $commandClass = $command::class;
        $this->dispatchedCommands[] = $command;

        // Check if we should throw an exception
        if (isset($this->exceptions[$commandClass])) {
            throw $this->exceptions[$commandClass];
        }

        // Check if we have a handler
        if (isset($this->handlers[$commandClass])) {
            return ($this->handlers[$commandClass])($command);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function registerHandler(string $commandClass, CommandHandlerInterface $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    /**
     * Register a handler callable for a command.
     *
     * @param class-string<Command> $commandClass
     */
    public function handle(string $commandClass, callable $handler): self
    {
        $this->handlers[$commandClass] = $handler;
        return $this;
    }

    /**
     * Set an exception to be thrown when a command is dispatched.
     *
     * @param class-string<Command> $commandClass
     */
    public function throwWhen(string $commandClass, \Throwable $exception): self
    {
        $this->exceptions[$commandClass] = $exception;
        return $this;
    }

    /**
     * Check if a command was dispatched.
     *
     * @param class-string<Command> $commandClass
     */
    public function hasDispatched(string $commandClass): bool
    {
        foreach ($this->dispatchedCommands as $command) {
            if ($command instanceof $commandClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the number of times a command was dispatched.
     *
     * @param class-string<Command> $commandClass
     */
    public function getDispatchCount(string $commandClass): int
    {
        $count = 0;
        foreach ($this->dispatchedCommands as $command) {
            if ($command instanceof $commandClass) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get all dispatched commands.
     *
     * @return array<Command>
     */
    public function getDispatchedCommands(): array
    {
        return $this->dispatchedCommands;
    }

    /**
     * Get the last dispatched command.
     */
    public function getLastCommand(): ?Command
    {
        $count = count($this->dispatchedCommands);
        return $count > 0 ? $this->dispatchedCommands[$count - 1] : null;
    }

    /**
     * Get dispatched commands of a specific type.
     *
     * @param class-string<Command> $commandClass
     * @return array<Command>
     */
    public function getDispatchedOfType(string $commandClass): array
    {
        return array_filter(
            $this->dispatchedCommands,
            fn(Command $command) => $command instanceof $commandClass
        );
    }

    /**
     * Reset the bus state.
     */
    public function reset(): void
    {
        $this->dispatchedCommands = [];
        $this->handlers = [];
        $this->exceptions = [];
    }

    /**
     * Assert that no commands were dispatched.
     *
     * @throws \RuntimeException if commands were dispatched
     */
    public function assertNothingDispatched(): void
    {
        if (count($this->dispatchedCommands) > 0) {
            $classes = array_map(
                fn(Command $c) => $c::class,
                $this->dispatchedCommands
            );
            throw new \RuntimeException(
                sprintf('Expected no commands to be dispatched, but [%s] were dispatched.', implode(', ', $classes))
            );
        }
    }
}
