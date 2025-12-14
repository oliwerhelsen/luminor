<?php

declare(strict_types=1);

namespace Luminor\Application\CQRS;

/**
 * Marker interface for commands.
 *
 * Commands represent intentions to change the state of the system.
 * They are named in the imperative mood (e.g., CreateUser, UpdateOrder)
 * and should contain all the data needed to perform the action.
 *
 * Commands:
 * - Express intent to change state
 * - Are named as verbs (CreateUser, DeleteOrder)
 * - Return void or a simple result (e.g., the created entity's ID)
 * - Should be validated before execution
 * - Are handled by exactly one CommandHandler
 */
interface Command
{
}
