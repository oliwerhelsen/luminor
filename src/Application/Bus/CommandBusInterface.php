<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\Bus;

use Luminor\DDD\Application\CQRS\Command;

/**
 * Interface for the command bus.
 *
 * The command bus routes commands to their appropriate handlers.
 * It provides a single entry point for executing commands and can
 * apply middleware for cross-cutting concerns like validation,
 * logging, and transaction management.
 */
interface CommandBusInterface
{
    /**
     * Dispatch a command to its handler.
     *
     * @template TResult
     * @param Command $command The command to dispatch
     * @return TResult The result from the command handler
     * @throws CommandHandlerNotFoundException If no handler is registered for the command
     */
    public function dispatch(Command $command): mixed;

    /**
     * Register a handler for a specific command type.
     *
     * @param class-string<Command> $commandClass The command class
     * @param CommandHandlerInterface $handler The handler instance
     */
    public function register(string $commandClass, CommandHandlerInterface $handler): void;

    /**
     * Register a handler using a callable resolver.
     *
     * The resolver will be called to create the handler instance when needed.
     *
     * @param class-string<Command> $commandClass The command class
     * @param callable(): CommandHandlerInterface $resolver The handler resolver
     */
    public function registerLazy(string $commandClass, callable $resolver): void;

    /**
     * Check if a handler is registered for a command type.
     *
     * @param class-string<Command> $commandClass The command class
     */
    public function hasHandler(string $commandClass): bool;
}
