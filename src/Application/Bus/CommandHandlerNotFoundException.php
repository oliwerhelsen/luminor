<?php

declare(strict_types=1);

namespace Lumina\DDD\Application\Bus;

use RuntimeException;
use Lumina\DDD\Application\CQRS\Command;

/**
 * Exception thrown when no handler is found for a command.
 */
final class CommandHandlerNotFoundException extends RuntimeException
{
    /**
     * Create exception for a command class.
     *
     * @param class-string<Command> $commandClass
     */
    public static function forCommand(string $commandClass): self
    {
        return new self(
            sprintf('No handler registered for command "%s"', $commandClass)
        );
    }
}
