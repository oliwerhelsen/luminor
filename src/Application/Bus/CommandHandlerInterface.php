<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\Bus;

use Luminor\DDD\Application\CQRS\Command;

/**
 * Interface for command handlers.
 *
 * Command handlers contain the application logic for processing commands.
 * Each handler is responsible for a single command type and orchestrates
 * the domain objects needed to fulfill the command.
 *
 * @template TCommand of Command
 * @template TResult
 */
interface CommandHandlerInterface
{
    /**
     * Handle a command and return the result.
     *
     * @param TCommand $command The command to handle
     *
     * @return TResult The result of the command execution
     */
    public function handle(Command $command): mixed;
}
