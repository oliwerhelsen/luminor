<?php

declare(strict_types=1);

namespace Lumina\DDD\Application\Validation;

use Lumina\DDD\Application\CQRS\Command;

/**
 * Interface for command validators.
 *
 * Validators check that commands contain valid data before they are
 * processed by their handlers.
 *
 * @template TCommand of Command
 */
interface ValidatorInterface
{
    /**
     * Validate a command.
     *
     * @param TCommand $command The command to validate
     * @return ValidationResult The validation result
     */
    public function validate(Command $command): ValidationResult;

    /**
     * Get the command class this validator handles.
     *
     * @return class-string<TCommand>
     */
    public function supports(): string;
}
